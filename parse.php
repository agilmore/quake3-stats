<?php

$BOTS = array(
  '<world>',
  'Patriot',
  'Angel',
  'Hunter',
  'Lucy',
  'Stripe',
  'Ranger',
  'Bitterman',
  'Uriel',
  'Grunt',
  'Phobos',
  'Slash',
  'Klesk',
  'Razor',
  'Daemia',
  'Sorlag',
  'Orbb',
  'Crash',
  'Xaero',
  'Major',
  'TankJr',
  'Mynx',
  'Keel',
  'Cadavre',
  'Bones',
  'Doom',
  'Gorre',
  'Biker',
  '^1A^2n^3a^4r^5k^6i',
  'Sarge',
  'Hossman',
  'Wrack',
  'Visor',
  'Rolf Harris'
);


/**
 * Functions
 */


function parse_stream($stream){
  $s_current_game = NULL;
  $s_blue_flag = -1;
  $s_red_flag = -1;
  
  $matches = array();

  while($line = stream_get_line($stream, 8189, "\n")){
    $line = trim($line);
    if(preg_match('/^(\d+:\d+)\s?([\w^]+):(?:\s(.*))?$/', $line, $matches)){
      switch($matches[2]){
        case 'InitGame':
          $s_current_game = new Game();
          $info = unpack_attributes($matches[3]);
          $s_current_game->setInfo($info);
          #$games[] = $s_current_game;
          break;
        case 'ClientConnect':
          $s_client = new Client((int) $matches[3]);
          $s_current_game->addClient($s_client);
          break;
        case 'ClientUserinfoChanged':
          $items = parse_user_info($matches[3]);
          $s_client = $s_current_game->getClient($items['client_id']);
          $s_client->setInfo($items['items']);
          break;
        case 'ClientBegin':
          $s_client = $s_current_game->getClient((int) $matches[3]);
          $s_client->setBeginTime($matches[1]);
          break;
        case 'ClientDisconnect':
          $s_client = $s_current_game->getClient((int) $matches[3]);
          $s_client->setLeaveTime($matches[1]);
          break;
        case 'Kill':
          $info = parse_kill_info($matches[3]);
          if($info['killer'] != $info['killed']){
            $s_client = $s_current_game->getClient($info['killer']);
            if(isset($s_client)){
              $s_client->incrementKillCount();
            }
          }
          $s_client = $s_current_game->getClient($info['killed']);
          if(isset($s_client)){
            $s_client->incrementDeathCount();
            #if($s_client->getId() == $s_blue_flag || $s_client->getId() == $s_red_flag){
              #echo "{$s_client->getName()} died whilst holding the flag?\n";
            #}
          }
          
          $kill = new Kill($s_current_game->getClient($info['killer']), $s_client = $s_current_game->getClient($info['killed']), $info['method_name']);
          $s_current_game->addKill($kill);
          
          break;
        case 'Item':
          list($client_id, $item) = explode(' ', $matches[3]);
          $s_client = $s_current_game->getClient($client_id);
          if($item == 'team_CTF_blueflag' || $item == 'team_CTF_redflag'){
            $flag_team = Client::TEAM_NONE;
            switch($item){
              case 'team_CTF_redflag': $flag_team = Client::TEAM_RED; break;
              case 'team_CTF_blueflag': $flag_team = Client::TEAM_BLUE; break;
            }
            
            $state_var = $flag_team == Client::TEAM_RED ? 's_red_flag' : 's_blue_flag';
            if($s_client->getTeam() == $flag_team){ //returning team flag.
              $s_client->incrementFlagReturnCount();
              $$state_var = -1;
            }
            else{
              $s_client->incrementFlagCaptureCount();
              $$state_var = $s_client->getId();
            }
          }
          break;
        case 'score':
          if($s_current_game->getInfo('g_gametype') == '4'){
            $score = parse_score($matches[3]);
            $s_client = $s_current_game->getClient($score['client_id']);
            $s_client->setCtfScore($score['score']);            
          }
          break;
        case 'ShutdownGame':
          return $s_current_game;
        default:
      }
    }
  }
  
  return FALSE;
}

function unpack_attributes($text){
  $text = trim($text);
  if(strpos($text, '\\') === 0){
    $text = substr($text, 1);
  }
  $parts = explode('\\', $text);
  $items = array();
  for($i = 0; $i < count($parts); $i+=2){
    $items[$parts[$i]] = $parts[$i+1];
  }
  return $items;
}

function sanitize_client_name($name){
  return preg_replace('/\^\d{1}/', '', $name);
}

function parse_user_info($text){
  list($client_id, $info) = explode(' ', $text, 2);
  $items = unpack_attributes($info);
  return array('client_id' => $client_id, 'items' => $items);
}

function parse_kill_info($text){
  $matches = array();
  if(preg_match('/^(\d+)\s(\d+)\s(\d+):\s([\w_<>\^ ]+)\skilled\s([\w_<>\^ ]+)\sby\s([\w_<>^]+)$/', $text, $matches)){
    return array(
      'killer' => (int) $matches[1],
      'killed' => (int) $matches[2],
      'method' => (int) $matches[3],
      'killer_name' => $matches[4],
      'killed_name' => $matches[5],
      'method_name' => $matches[6],
    );
  }
}

function parse_score($text){
  $matches = array();
  if(preg_match('/^([-\d]+)\s+ping:\s+(\d+)\s+client:\s+(\d+)\s+([\w_<>\^ ]+)$/', $text, $matches)){
    return array(
      'score' => (int) $matches[1],
      'ping' => (int) $matches[2],
      'client_id' => (int) $matches[3],
      'client_name' => $matches[4],
    );
  }
}

/*
 * Classes
 */

class Client{
  const TEAM_NONE = -1;
  const TEAM_RED = 1;
  const TEAM_BLUE = 2;

  private $id;
  private $name;
  private $info;
  private $beginTime;
  private $leaveTime;
  private $killCount = 0;
  private $deathCount = 0;
  private $humiliationCount = 0;
  private $ctfScore = 0;
  private $team;
  private $flagCapture;
  private $flagReturn;

  function __construct($client_id){
    $this->id = $client_id;
  }
  
  function getId(){
    return $this->id;
  }
  
  function getName(){
    return $this->name;
  }
  
  function setName($name){
    $this->name = $name;
  }
  
  function getBeginTime(){
    return $this->beginTime;
  }
  
  function setBeginTime($time){
    $this->beginTime = $time;
  }
  
  function getLeaveTime(){
    return $this->leaveTime;
  }
  
  function setLeaveTime($time){
    $this->leaveTime = $time;
  }
  
  function getTeam(){
    return $this->team;
  }
  
  function setTeam($team){
    $this->team = $team;
  }
  
  function getInfo($name = NULL){
    if(!empty($name)){
      return $this->info[$name];
    }
    else{
      return $this->info;
    }
  }
  
  function setInfo($info){
    $this->info = $info;
    if(!empty($info['n'])){
      $this->setName($info['n']);
    }
    if(!empty($info['t'])){
      $this->setTeam($info['t']);
    }
  }
  
  function getKillCount(){
    return $this->killCount;
  }
  
  function incrementKillCount(){
    $this->killCount++;
  }
  
  function getDeathCount(){
    return $this->deathCount;
  }
  
  function incrementDeathCount(){
    $this->deathCount++;
  }
  
  function getCtfScore(){
    return $this->ctfScore;
  }
  
  function setCtfScore($ctfScore){
    $this->ctfScore = $ctfScore;
  }
  
  function getFlagCaptureCount(){
    return $this->flagCapture;
  }
  
  function incrementFlagCaptureCount(){
    $this->flagCapture++;
  }
  
  function getFlagReturnCount(){
    return $this->flagReturn;
  }
  
  function incrementFlagReturnCount(){
    $this->flagReturn++;
  }
  
  function __toString(){
    $output = sprintf(
      "% 2d) %s DM (Kills: %d, Deaths: %d) CTF (Score: %d)",
      $this->id,
      sanitize_client_name($this->name),
      $this->killCount,
      $this->deathCount,
      $this->ctfScore
    );
    return $output;
  }
}

class Game{
  private $clients;
  private $kills;
  private $info;
  
  private $world;

  function __construct(){
    $this->clients = array();
    $this->kills = array();
  }
  
  function addClient(&$client){
    $this->clients[$client->getId()] = $client;
  }
  
  function getClient($client_id){
    if($client_id == 1022){
      if($this->world == NULL){
        $this->world = new Client(1022);
        $this->world->setName("<world>");
      }
      return $this->world;
    }
    else if(isset($this->clients[$client_id])){
      return $this->clients[$client_id];
    }
    else{
      return NULL;
    }
  }
  
  function getClients(){
    return $this->clients;
  }
  
  function setInfo($info){
    $this->info = $info;
  }
  
  function getInfo($name = NULL){
    if(!empty($name)){
      return $this->info[$name];
    }
    else{
      return $this->info;
    }
  }
  
  function addKill(Kill $kill){
    $this->kills[] = $kill;
  }
  
  function getKills($killer = NULL, $killed = NULL, $method = NULL){
    $kills = $this->kills;
    if($killer != NULL){
      $killer_obj = $this->getClient($killer);
      $killer_id = $killer_obj->getId();
      $kills = array_filter($kills, function($kill) use ($killer_id){
        if($kill->getKiller()->getId() == $killer_id){
          return $kill;
        }
      });
    }
    
    if($killed != NULL){
      $killed_obj = $this->getClient($killed);
      $kills = array_filter($kills, function($kill){
        if($kill->getKiller() == $killed_obj){
          return $kill;
        }
      });
    }
    
    if($method != NULL){
      $kills = array_filter($kills, function($kill) use ($method){
        if($kill->getMethod() == $method){
          return $kill;
        }
      });
    }
    
    return $kills;
  }
  
  function __toString(){
    $output = "GAME:\n";
    if(!empty($this->info['mapname'])){
      $output .= 'Map: ' . $this->info['mapname'] . "\n";
    }
    if(!empty($this->clients)){
      $output .= "Players:\n";
      foreach($this->clients as $client){
        $output .= "\t" . $client . "\n";
      }
    }
    else{
      $output .= "\tThe game was empty.";
    }
    return $output;
  }
}

class Kill{
  const MOD_UNKNOWN = 0;
  const MOD_SHOTGUN = 1;
  const MOD_GAUNTLET = 2;
  const MOD_MACHINEGUN = 3;
  const MOD_GRENADE = 4;
  const MOD_GRENADE_SPLASH = 5;
  const MOD_ROCKET = 6;
  const MOD_ROCKET_SPLASH = 7;
  const MOD_PLASMA = 8;
  const MOD_PLASMA_SPLASH = 9;
  const MOD_RAILGUN = 10;
  const MOD_LIGHTNING = 11;
  const MOD_BFG = 12;
  const MOD_BFG_SPLASH = 13;
  const MOD_WATER = 14;
  const MOD_SLIME = 15;
  const MOD_LAVA = 16;
  const MOD_CRUSH = 17;
  const MOD_TELEFRAG = 18;
  const MOD_FALLING = 19;
  const MOD_SUICIDE = 20;
  const MOD_TARGET_LASER = 21;
  const MOD_TRIGGER_HURT = 22;
  #ifdef MISSIONPACK
  const MOD_NAIL = 23;
  const MOD_CHAINGUN = 24;
  const MOD_PROXIMITY_MINE = 25;
  const MOD_KAMIKAZE = 26;
  const MOD_JUICED = 27;
  #endif
  const MOD_GRAPPLE = 28;
  
  public static function getMethods(){
    $reflect = new ReflectionClass('Kill');
    return $reflect->getConstants();
  }

  private $killer;
  private $killed;
  private $method;

  public function __construct(Client $killer, Client $killed, $method){
    if(is_null($method)){
      throw new Exception("One or more arguments was null.");
    }
    $this->killer = $killer;
    $this->killed = $killed;
    if(is_string($method)){
      $methods = Kill::getMethods();
      if(isset($methods[$method])){
        $this->method = $methods[$method];
      }
      else if(is_int($method)){
        $this->method = $method;
      }
      else{
        var_dump($method);
      }
    }
  }
  
  public function getKiller(){
    return $this->killer;
  }
  
  public function getKilled(){
    return $this->killed;
  }
  
  public function getMethod(){
    return $this->method;
  }
  
  function __toString(){
    $method_name = array_search($this->method, Kill::getMethods());
    return $this->killer . " killed " . $this->killed . " by " . $method_name;
  }
}
