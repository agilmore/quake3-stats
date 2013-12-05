<?php

require_once('parse.php');

$fh = fopen('php://stdin', 'r');

$games = array();
while($game = parse_stream($fh)){
  $games[] = $game;
  echo $game;
  echo "----------\n";
}

$index_by_name = array();

echo "Summary\n";
foreach($games as $game){
  $clients = $game->getClients();
  #var_dump($clients);
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
    }
  }
}

array_walk($index_by_name, function(&$v, $i){
  $v['total'] = $v['kills'] - $v['deaths'];
});
uasort($index_by_name, function($a, $b){
  return $b['total'] - $a['total'];
});

echo "Player Name | Kills | Deaths | Total\n";
foreach($index_by_name as $player_name => $stats){
  printf("% -11s | % 5d | % 6d | %+ 5d\n", sanitize_client_name($player_name), $stats['kills'], $stats['deaths'], $stats['kills'] - $stats['deaths']);
}
