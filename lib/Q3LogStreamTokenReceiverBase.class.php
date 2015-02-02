<?php

require_once('Q3LogStreamTokenReceiver.interface.php');

abstract class Q3LogStreamTokenReceiverBase implements Q3LogStreamTokenReceiver {
  public abstract function acceptToken(array $token);
}
