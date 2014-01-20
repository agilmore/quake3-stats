<?php

define('START_SCORE', 1000);
define('K_FACTOR', 8);

require_once('parse.php');

$fh = fopen('php://stdin', 'r');

$games = array();
while($game = parse_stream($fh)){
  $games[] = $game;
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
      if(empty($rating_index[$killer_name])){
        $rating_index[$killer_name] = START_SCORE;
      }
      if(empty($rating_index[$killed_name])){
        $rating_index[$killed_name] = START_SCORE;
      }
      
      $e = calculate_expected_score($rating_index[$killer_name], $rating_index[$killed_name]);
      $score = 1;
      $rating_index[$killer_name] = (int) round($rating_index[$killer_name] + (K_FACTOR * ($score - $e)));
      
      $e = calculate_expected_score($rating_index[$killed_name], $rating_index[$killer_name]);
      $score = 0;
      $rating_index[$killed_name] = (int) round($rating_index[$killed_name] + (K_FACTOR * ($score - $e)));
    }
  }
  
}

function calculate_expected_score($my_rating, $op_rating){
  return 1 / (1 + pow(10, ($op_rating - $my_rating) / 400.0) );
}


// Sort
uasort($rating_index, function($a, $b){
  return $b - $a;
});

echo "Player Name\t|\tELO Rating\n";
foreach($rating_index as $player_name => $rating){
  $player_name = sanitize_client_name($player_name);
  if(strlen($player_name) > 11){
    $player_name = substr($player_name, 0, 8) . '...';
  }
  printf("% -11s\t|\t% 10d\n", $player_name, $rating);
}

