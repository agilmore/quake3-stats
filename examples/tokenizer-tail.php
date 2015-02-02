<?php

require(dirname(__FILE__) . '/../lib/Q3LogStreamTokenizer.class.php');

$fh = fopen('php://stdin', 'r');
$tokenizer = new Q3LogStreamTokenizer($fh);
$tokenizer->addTokenCallback(function($token) {
  if($token['type'] == Q3LogStreamTokenizer::T_UNKNOWN) var_dump($token);
});
$tokenizer->start();

echo "\nEND\n";

