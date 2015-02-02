<?php

class Q3LogStreamTokenizer {
  
  const T_INIT_GAME = 0;
  const T_CLIENT_CONNECT = 1;
  const T_CLIENT_USER_INFO_CHANGED = 2;
  const T_CLIENT_BEGIN = 3;
  const T_CLIENT_DISCONNECT = 4;
  const T_ITEM = 5;
  const T_KILL = 6;
  const T_SHUTDOWN_GAME = 7;
  const T_EXIT = 8;
  const T_CHAT = 9;
  const T_SCORE = 10;
  const T_WARMUP = 11;
  
  const T_UNKNOWN = -1;
  
  const IN_LOGFILE = 1;
  const IN_STDOUT = 2;


  private $stream;
  private $inputFormat;
  private $receiver;

  public function __construct($stream, $format = IN_LOGFILE) {
    $this->stream = $stream;
    $this->inputFormat = $format;
    $this->callbacks = array();
  }
  
  function registerTokenReceiver(Q3LogStreamTokenReceiver $receiver) {
    $this->receiver[] = $receiver;
  }
  
  private function callReceivers($token) {
    foreach($this->receiver as $receiver) {
      $receiver->acceptToken($token);
    }
  }
  
  private function acceptToken($token) {
    $this->callReceivers($token);
  }
  
  private function unpack_attributes($text){
    $text = trim($text);
    if(strpos($text, '\\') === 0){
      $text = substr($text, 1);
    }
    $parts = explode('\\', $text);
    $items = array();
    for($i = 0; $i < count($parts); $i+=2){
      if(isset($parts[$i+1])){
        $items[$parts[$i]] = $parts[$i+1];
      }
    }
    return $items;
  }

  private function sanitize_client_name($name){
    return preg_replace('/\^\d{1}/', '', $name);
  }

  private function parse_user_info($text){
    list($client_id, $info) = explode(' ', $text, 2);
    $items = $this->unpack_attributes($info);
    return array('client_id' => $client_id, 'items' => $items);
  }

  private function parse_kill_info($text){
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

  private function parse_score($text){
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
  
  public function start() {
    $matches = array();

    var_dump($this);
    while($line = fgets($this->stream, 2048)){
      $line = trim($line);
      //var_dump($line);
      $regex = null;
      if ($this->inputFormat == self::IN_LOGFILE) {
        $regex = '/^(?:\d+:\d+)\s?([\w^]+):(?:\s(.*))?$/';
      }
      else if ($this->inputFormat == self::IN_STDOUT) {
        $regex = '/^([\w^]+):(?:\s(.*))?$/';
      }
      if (preg_match($regex, $line, $matches)) {
        $token = array(
          'raw' => $line,
          //'log_time' => $matches[1],
        );
        switch($matches[1]){
          case 'InitGame':
            $info = $this->unpack_attributes($matches[2]);
            $token['type'] = self::T_INIT_GAME;
            $token['config'] = $info;
            break;
          case 'ClientConnect':
            $client_id = (int) $matches[2];
            $token['type'] = self::T_CLIENT_CONNECT;
            $token['client_id'] = $client_id;
            break;
          case 'ClientUserinfoChanged':
            $items = $this->parse_user_info($matches[2]);
            $token['type'] = self::T_CLIENT_USER_INFO_CHANGED;
            $token['client_id'] = $items['client_id'];
            $token['client_info'] = $items['items'];
            break;
          case 'ClientBegin':
            $client_id = (int) $matches[2];
            $token['type'] = self::T_CLIENT_BEGIN;
            $token['client_id'] = $client_id;
            break;
          case 'ClientDisconnect':
            $client_id = (int) $matches[2];
            $token['type'] = self::T_CLIENT_DISCONNECT;
            $token['client_id'] = $client_id;
            break;
          case 'Kill':
            $info = $this->parse_kill_info($matches[2]);
            $token['type'] = self::T_KILL;
            $token['info'] = $info;
            break;
          case 'Item':
            list($client_id, $item) = explode(' ', $matches[2]);
            $token['type'] = self::T_ITEM;
            $token['client_id'] = $client_id;
            $token['item'] = $item;
            break;
          case 'score':
            $score = $this->parse_score($matches[2]);
            $token['type'] = self::T_SCORE;
            $token += $score;
            break;
          case 'Exit':
            $token['type'] = self::T_EXIT;
            $token['reason'] = $matches[2];
            break;
          case 'ShutdownGame':
            $token['type'] = self::T_SHUTDOWN_GAME;
            break;
          case 'Warmup':
            $token['type'] = self::T_WARMUP;
            break;
          case 'sayteam':
          case 'say':
          case 'tell':
            //var_dump($matches);
            list($client_name, $message) = explode(':', $matches[2], 2);
            $token['type'] = self::T_CHAT;
            $token['method'] = $matches[1];
            $token['client_name'] = $client_name;
            $token['message'] = trim($message);
            break;
          default:
            $token['type'] = self::T_UNKNOWN;
        }
        $this->acceptToken($token);
      }
    }
  }
  
  public static function getTokenTypeString($type) {
    $reflect = new ReflectionClass('Q3LogStreamTokenizer');
    $constants = $reflect->getConstants();
    return array_search($type, $constants);
  }
}
