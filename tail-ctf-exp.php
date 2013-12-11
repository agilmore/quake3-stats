<?php

require_once('parse.php');

$fh = fopen('php://stdin', 'r');

$games = array();
while($game = parse_stream($fh)){
  $games[] = $game;
}

$index_by_name = array();

echo "CTF Summary\n";
foreach($games as $game){
  if($game->getInfo('g_gametype') != '4') continue;
  $clients = $game->getClients();
  if(!empty($clients) && is_array($clients)){
    foreach($clients as $client){
      $client_name = $client->getName();
      if(in_array($client_name, $BOTS)){
        continue;
      }
      if(!isset($index_by_name[$client_name])){
        $index_by_name[$client_name] = array('captures' => 0, 'returns' => 0, 'kills' => 0, 'deaths' => 0, 'score' => 0);
      }
      $index_by_name[$client_name]['captures'] += $client->getFlagCaptureCount();
      $index_by_name[$client_name]['returns'] += $client->getFlagReturnCount();
      $index_by_name[$client_name]['kills'] += $client->getKillCount();
      $index_by_name[$client_name]['deaths'] += $client->getDeathCount();
      $index_by_name[$client_name]['score'] += $client->getCtfScore();
    }
  }
}

// Sort by total
uasort($index_by_name, function($a, $b){
  return $b['score'] - $a['score'];
});

$bold = `tput bold`;
$normal = `tput sgr0`;
echo "{$bold}Player Name | Pickups | Returns | Kills | Deaths | Total | Score\n{$normal}";
foreach($index_by_name as $player_name => $stats){
  printf("% -11s | % 7d | % 7d | % 5d | % 6d | %+ 5d | {$bold}% 5d{$normal}\n", sanitize_client_name($player_name), $stats['captures'], $stats['returns'], $stats['kills'], $stats['deaths'], $stats['kills'] - $stats['deaths'], $stats['score']);
}
