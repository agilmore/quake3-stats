<?php

define('START_SCORE', 1000);
define('K_FACTOR', 16);

require_once('parse.php');

$fh = fopen('php://stdin', 'r');

$games = array();
while($game = parse_stream($fh)){
  $games[] = $game;
  #echo $game;
  #echo "----------\n";
}

$score_index = array();
$rating_index = array();

echo "ELO Rating Board\n";
foreach($games as $game){
  if($game->getInfo('g_gametype') != '0') continue;
  
  $methods = Kill::getMethods();
  
  $kills = $game->getKills();
  foreach($kills as $kill){
    
    $killer_name = $kill->getKiller()->getName();
    $killed_name = $kill->getKilled()->getName();
    if(!in_array($killer_name, $BOTS) && !in_array($killed_name, $BOTS)){
      if(!isset($score_index[$killer_name][$killed_name])){
        $score_index[$killer_name][$killed_name] = 0;
      }
      $score_index[$killer_name][$killed_name] += 1;
    
      if(empty($rating_index[$killer_name])){
        $rating_index[$killer_name] = START_SCORE;
      }
      if(empty($rating_index[$killed_name])){
        $rating_index[$killed_name] = START_SCORE;
      }
    }
    
    //var_dump($score_index);
  }
  
  foreach($score_index as $killer_name => $scores){
    foreach($scores as $killed_name => $score){
      $e = calculate_expected_score($rating_index[$killer_name], $rating_index[$killed_name]);
      echo "Score $score, Expected $e\n";
      $rating_index[$killer_name] = $rating_index[$killer_name] + (K_FACTOR * ($score - $e));
    }
  }
  
}

function calculate_expected_score($my_rating, $op_rating){
  echo "calculate_expected_score($my_rating, $op_rating)\n";
  
  return 1 / (1 + (pow(10, round($op_rating - $my_rating) / 400)));
}

var_dump($rating_index);
die();
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
  $v['netto'] = ((float) ($v['kills'] - $v['deaths'])) / ((float) ($v['kills'] + $v['deaths']));
});
// Sort by total
uasort($index_by_name, function($a, $b){
  $d = $b['netto'] - $a['netto'];
  return $d < 0 ? -1 : ($d > 0 ? 1 : 0);
});

echo "Player Name\t|\tKills\t|\tDeaths\t|\tTotal\t|\tNetto\n";
foreach($index_by_name as $player_name => $stats){
  printf("% -11s\t|\t% 5d\t|\t% 6d\t|\t%+ 5d\t|\t% 5.2f\n", sanitize_client_name($player_name), $stats['kills'], $stats['deaths'], $stats['total'], $stats['netto']);
}
