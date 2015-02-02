<?php
require_once('lib/Q3LogStreamTokenizer.class.php');
require_once('lib/DBStatisticsBuilder.class.php');
require_once('lib/CommandBot.class.php');

$descriptorspec = array(
   0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
   1 => array("pipe", "w"),  // stdout is a pipe that the child will write to
   2 => array("pipe", "w")   // stderr is a file to write to
);
$pipes = array();

$process = proc_open($argv[1], $descriptorspec, $pipes, getcwd());

if (is_resource($process)) {
  list($in, $out, $err) = $pipes;

  $tokenizer = new Q3LogStreamTokenizer($err, Q3LogStreamTokenizer::IN_STDOUT);
  
  $tokenizer->registerTokenReceiver(new CommandBot($in));
  $tokenizer->registerTokenReceiver(new DBStatisticsBuilder());
  
  $tokenizer->start();

  fclose($in);
  fclose($out);
  fclose($err);

  $return_value = proc_close($process);

  var_dump($return_value);
}
else {
  echo "Error";
}
