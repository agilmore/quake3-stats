<?php

require_once('parse.php');

$fh = fopen('php://stdin', 'r');

$s_filter_weapon = NULL;
for($i = 0; $i < $argc; $i++){
  if($argv[$i] == '-w'){
    $s_filter_weapon = $argv[++$i];
  }
}

if($s_filter_weapon == NULL){
  die('No weapon selected, use -w <weapon>');
}

$s_filter_weapon = explode(',', $s_filter_weapon);

$games = array();
while($game = parse_stream($fh)){
  $games[] = $game;
  #echo $game;
  #echo "----------\n";
}

$index_by_name = array();

echo implode(',', $s_filter_weapon), " Summary\n";
foreach($games as $game){
  if($game->getInfo('g_gametype') != '0') continue;
  $methods = Kill::getMethods();
  foreach($s_filter_weapon as $weapon){
    $kills = $game->getKills(NULL, NULL, $methods[$weapon]);
    foreach($kills as $kill){
      $client_name = $kill->getKiller()->getName();
      if(in_array($client_name, $BOTS)){
        continue;
      }
      if(!isset($index_by_name[$client_name])){
        $index_by_name[$client_name] = array('kills' => 0, 'deaths' => 0);
      }
      $index_by_name[$client_name]['kills'] += 1;
      
      $client_name = $kill->getKilled()->getName();
      if(in_array($client_name, $BOTS)){
        continue;
      }
      if(!isset($index_by_name[$client_name])){
        $index_by_name[$client_name] = array('kills' => 0, 'deaths' => 0);
      }
      $index_by_name[$client_name]['deaths'] += 1;
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
  if($v['deaths'] == 0){
    $v['ratio'] = 1;
  }
  else{
    $v['ratio'] = $v['kills'] / $v['deaths'];
  }
});
// Sort by total
uasort($index_by_name, function($a, $b){
  return $b['total'] - $a['total'];
});

echo "Player Name\t|\tKills\t|\tDeaths\t|\tTotal\t|\tRatio\n";
foreach($index_by_name as $player_name => $stats){
  printf("% -11s\t|\t% 5d\t|\t% 6d\t|\t%+ 5d\t|\t% 5.2f\n", sanitize_client_name($player_name), $stats['kills'], $stats['deaths'], $stats['total'], $stats['ratio']);
}
