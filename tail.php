<?php

require_once('parse.php');

$fh = fopen('php://stdin', 'r');

$games = array();
while($game = parse_stream($fh)){
  $games[] = $game;
  #echo $game;
  #echo "----------\n";
}

$index_by_name = array();

echo "Summary\n";
foreach($games as $game){
  if($game->getInfo('g_gametype') != '0') continue;
  $clients = $game->getClients();
  if(!empty($clients) && is_array($clients)){
    foreach($clients as $client){
      $client_name = $client->getName();
      if(in_array($client_name, $BOTS)){
        continue;
      }
      if(!isset($index_by_name[$client_name])){
        $index_by_name[$client_name] = array('kills' => 0, 'deaths' => 0);
      }
      $index_by_name[$client_name]['kills'] += $client->getKillCount();
      $index_by_name[$client_name]['deaths'] += $client->getDeathCount();
      
      #var_dump($game->getKills($client->getId()));
    }
  }
}

// Exclude players with 0 kills and deaths.
$index_by_name = array_filter($index_by_name, function($v){
  return $v['kills'] > 0 || $v['deaths'] > 0;
});
// Calculate total
array_walk($index_by_name, function(&$v, $i){
  $v['total'] = $v['kills'] - $v['deaths'];
  $v['ratio'] = $v['kills'] / $v['deaths'];
});
// Sort by total
uasort($index_by_name, function($a, $b){
  return $b['total'] - $a['total'];
});

echo "Player Name\t|\tKills\t|\tDeaths\t|\tTotal\t|\tRatio\n";
foreach($index_by_name as $player_name => $stats){
  printf("% -11s\t|\t% 5d\t|\t% 6d\t|\t%+ 5d\t|\t% 5.2f\n", sanitize_client_name($player_name), $stats['kills'], $stats['deaths'], $stats['total'], $stats['ratio']);
}
