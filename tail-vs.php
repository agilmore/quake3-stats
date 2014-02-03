<?php

require_once('parse.php');

$fh = fopen('php://stdin', 'r');

$player1 = $argv[1];
$player2 = $argv[2];

if(empty($player1) && empty($player2)){
  die('Players not properly defined.');
}

$index_by_name = array();
$index_by_name[$player1] = array('kills' => 0, 'deaths' => 0);
$index_by_name[$player2] = array('kills' => 0, 'deaths' => 0);

while($game = parse_stream($fh)){
  if($game->getInfo('g_gametype') != '0') continue;
  $methods = Kill::getMethods();
  
  $kills = $game->getKills($player1, $player2, NULL);
  #var_dump($kills);
  foreach($kills as $kill){
    $client_name = $kill->getKiller()->getName();
    $index_by_name[$client_name]['kills'] += 1;
    
    $client_name = $kill->getKilled()->getName();
    $index_by_name[$client_name]['deaths'] += 1;
  }
  
  $kills = $game->getKills($player2, $player1, NULL);
  foreach($kills as $kill){
    $client_name = $kill->getKiller()->getName();
    $index_by_name[$client_name]['kills'] += 1;
    
    $client_name = $kill->getKilled()->getName();
    $index_by_name[$client_name]['deaths'] += 1;
  }
}

// Calculate total
array_walk($index_by_name, function(&$v, $i){
  $v['total'] = $v['kills'] - $v['deaths'];
  if($v['deaths'] == 0){
    $v['ratio'] = 1;
  }
  else{
    $v['ratio'] = $v['kills'] / $v['deaths'];
  }
  $v['netto'] = ((float) ($v['kills'] - $v['deaths'])) / ((float) ($v['kills'] + $v['deaths']));
});
// Sort by total
uasort($index_by_name, function($a, $b){
  $d = $b['netto'] - $a['netto'];
  return $d < 0 ? -1 : ($d > 0 ? 1 : 0);
});

echo $player1, " vs ", $player2, "\n";

$total = $index_by_name[$player1]['kills'] + $index_by_name[$player2]['kills'];

echo "Player Name\t|\tKills\t|\tDeaths\t|\tTotal\t|\tNetto\t|\tPercent\n";
foreach($index_by_name as $player_name => $stats){
  $player_name = sanitize_client_name($player_name);
  if(strlen($player_name) > 11){
    $player_name = substr($player_name, 0, 8) . '...';
  }
  printf("% -11s\t|\t% 5d\t|\t% 6d\t|\t%+ 5d\t|\t% 5.2f\t|\t% 5.2f%%\n", $player_name, $stats['kills'], $stats['deaths'], $stats['total'], $stats['netto'], ($stats['kills'] / $total) * 100);
}
