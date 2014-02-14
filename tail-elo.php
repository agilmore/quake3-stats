<?php

define('START_SCORE', 1000);
define('K_FACTOR', 4);

require_once('parse.php');

function calculate_expected_score($my_rating, $op_rating){
  return 1 / (1 + pow(10, ($op_rating - $my_rating) / 400.0) );
}

$fh = fopen('php://stdin', 'r');

$games = array();
while($game = parse_stream($fh)){
  $games[] = $game;
}

$score_index = array();
$rating_index = array();
$missed_index = array();

echo "Elo Rating Board\n";
foreach($games as $game){
  if($game->getInfo('g_gametype') != '0') continue;
  
  $methods = Kill::getMethods();
  
  $kills = $game->getKills();
  foreach($kills as $kill){
    $killer_name = sanitize_client_name($kill->getKiller()->getName());
    $killed_name = sanitize_client_name($kill->getKilled()->getName());
    if(!in_array($killer_name, $BOTS) && !in_array($killed_name, $BOTS)){
      if(empty($rating_index[$killer_name])){
        $rating_index[$killer_name] = START_SCORE;
      }
      if(empty($rating_index[$killed_name])){
        $rating_index[$killed_name] = START_SCORE;
      }
      
      if($killer_name != $killed_name){ // Don't award win to player who kills themself
        $e = calculate_expected_score($rating_index[$killer_name], $rating_index[$killed_name]);
        $score = 1;
        $rating_index[$killer_name] = (int) round($rating_index[$killer_name] + (K_FACTOR * ($score - $e)));
      } // ...but still award lose.
      
      $e = calculate_expected_score($rating_index[$killed_name], $rating_index[$killer_name]);
      $score = 0;
      $rating_index[$killed_name] = (int) round($rating_index[$killed_name] + (K_FACTOR * ($score - $e)));
    }
  }
  
  $players_names = array();
  foreach($game->getClients() as $client){
    $players_names[] = $client->getName();
  }
  
  $rated_player_names = array_keys($rating_index);
  foreach(array_diff($rated_player_names, $players_names) as $missing_player){
    $depreciate = $rating_index[$missing_player] * 0.001;
    #$rating_index[$missing_player] -= $depreciate;
    
    $redist = round($depreciate / count($players_names));
    if($redist > 0){
      foreach($players_names as $player_name){
        if(isset($rating_index[$player_name])){
          #$rating_index[$player_name] += $redist;
        }
      }
    }
  }
}

// Sort
uksort($rating_index, 'strcasecmp');
uasort($rating_index, function($a, $b){
  return $b - $a;
});

$bold = `tput bold`;
$normal = `tput sgr0`;
echo "{$bold} # | Player Name\t|\tElo Rating{$normal}\n";
$pos = 1;
foreach($rating_index as $player_name => $rating){
  if(strlen($player_name) > 11){
    $player_name = substr($player_name, 0, 8) . '...';
  }
  if($pos == 1) echo `tput setf 2`;
  printf("% 2d | % -11s\t|\t% 10d\n", $pos, $player_name, $rating);
  if($pos == 1) echo $normal;
  $pos++;
}

