<?php

require_once('parse.php');

$fh = fopen('php://stdin', 'r');

$index_by_name = array();
$game_count = 0;
$i = 0;
while($game = parse_stream($fh)){
  if($game->getInfo('g_gametype') != '0') continue;
  $i++;
  $clients = $game->getClients();
  if(!empty($clients) && is_array($clients)){
    $winner = NULL;
    foreach($clients as $client){
      $client_name = $client->getName();
      if(in_array($client_name, $BOTS)){
        continue;
      }
      if(!isset($index_by_name[$client_name])){
        $index_by_name[$client_name] = array('wins' => 0, 'games' => 0);
      }
      $index_by_name[$client_name]['games'] += 1;
      
      if(is_null($winner) || $client->getCtfScore() > $winner->getCtfScore()){
        $winner = $client;
      }
    }
    
    if(is_object($winner) && $winner->getCtfScore() > 0){
      $client_name = $winner->getName();
      if(isset($index_by_name[$client_name]['wins'])){
        $index_by_name[$client_name]['wins'] += 1;
      }
      else{
        $index_by_name[$client_name]['wins'] = 1;
        var_dump($client_name);
      }
      $game_count++;
    }
  }
}

echo $game_count, " games counted\n";

// Exclude players with 0 kills and deaths.
#$index_by_name = array_filter($index_by_name, function($v){
#  return $v['kills'] > 0 || $v['deaths'] > 0;
#});
// Calculate total
#array_walk($index_by_name, function(&$v, $i){
#  $v['total'] = $v['kills'] - $v['deaths'];
#  $v['ratio'] = (float) $v['kills'] / $v['deaths'];
#  $v['netto'] = ((float) ($v['kills'] - $v['deaths'])) / ((float) ($v['kills'] + $v['deaths']));
#  $v['astat'] = $v['netto'] * $v['games'];
#});
// Sort by total
uasort($index_by_name, function($a, $b){
  $d = $b['wins'] - $a['wins'];
  $pass1 = $d < 0 ? -1 : ($d > 0 ? 1 : 0);
  if($pass1 == 0){
    return $a['games'] - $b['games'];
  }
  else{
    return $pass1;
  }
});

// (frags - death) / (frags + deaths)

echo "Winner Summary\n";
echo "Player Name\t|\tWins\t|\tGames\n";
foreach($index_by_name as $player_name => $stats){
  if(strlen($player_name) > 11){
    $player_name = substr($player_name, 0, 8) . '...';
  }
  printf("% -11s\t|\t% 4d\t|\t% 5d\n", $player_name, $stats['wins'], $stats['games']);
}
