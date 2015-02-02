<?php

require_once('Q3LogStreamTokenReceiver.interface.php');

class CommandBot extends Q3LogStreamTokenReceiverBase {

  private $out;

  public function __construct($out) {
    $this->out = $out;
  }

  public function acceptToken(array $token) {
    if ($token['type'] == Q3LogStreamTokenizer::T_CHAT) {
      if (strpos($token['message'], '!') === 0) {
        switch($token['message']) {
          case '!help':
            fwrite($this->out, "Accepted commands: !help, !restart, !time, !next\n");
            break;
          case '!restart':
            fwrite($this->out, "map_restart\n");
            break;
          case '!time':
            fwrite($this->out, "Current time: " . date('H:i') . "\n");
            break;
          case '!next':
            fwrite($this->out, "vstr nextmap\n");
            break;
          default:
            fwrite($this->out, "Not a recognised command\n");
        }
      }
    }
  }
}
