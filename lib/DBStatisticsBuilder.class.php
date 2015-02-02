<?php

require_once('Q3LogStreamTokenReceiverBase.class.php');
require_once('Q3LogStreamTokenizer.class.php');

class DBStatisticsBuilder extends Q3LogStreamTokenReceiverBase {
  private $s_game = null;

  public function acceptToken(array $token) {
    echo "[", Q3LogStreamTokenizer::getTokenTypeString($token['type']), ':', print_r($token, true), "]\n";
    
    switch ($token['type']) {
      case Q3LogStreamTokenizer::T_INIT_GAME:
        $config = $token['config'];
        if ($config['g_gametype'] == 1) {
          if (!empty($s_game) {
            echo "Error: The previous game did not end!";
          }
          // INSERT INTO Game (hostname, gametype, gamename, mapname, fraglimit, timelimit) VALUES ();
        }
        break;
      case Q3LogStreamTokenizer::T_SHUTDOWN_GAME:
        
        break;
    }
  }
}
