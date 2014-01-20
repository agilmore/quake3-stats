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
        $index_by_name[$client_name] = array('kills' => 0, 'deaths' => 0, 'games' => 0);
      }
      $index_by_name[$client_name]['kills'] += $client->getKillCount();
      $index_by_name[$client_name]['deaths'] += $client->getDeathCount();
      $index_by_name[$client_name]['games'] += 1;
      
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
  $v['ratio'] = (float) $v['kills'] / $v['deaths'];
  $v['netto'] = ((float) ($v['kills'] - $v['deaths'])) / ((float) ($v['kills'] + $v['deaths']));
  $v['astat'] = $v['netto'] * $v['games'];
});
// Sort by total
uasort($index_by_name, function($a, $b){
  $d = $b['netto'] - $a['netto'];
  return $d < 0 ? -1 : ($d > 0 ? 1 : 0);
});

// (frags - death) / (frags + deaths)

echo "Player Name\t|\tKills\t|\tDeaths\t|\tTotal\t|\tNetto\t|\tAStat (tm)\n";
foreach($index_by_name as $player_name => $stats){
  $player_name = sanitize_client_name($player_name);
  if(strlen($player_name) > 11){
    $player_name = substr($player_name, 0, 8) . '...';
  }
  printf("% -11s\t|\t% 5d\t|\t% 6d\t|\t%+ 5d\t|\t% 5.2f\t|\t% 5.2f\n", $player_name, $stats['kills'], $stats['deaths'], $stats['total'], $stats['netto'], $stats['astat']);
}
