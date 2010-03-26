<?php

# Script to test functions.

# Required uploader include files
require_once('php_batch_uploader/facebook.inc.php');
require_once('php_batch_uploader/functions.inc.php');
require_once('php_batch_uploader/help.inc.php');
require_once('php_batch_uploader/images.inc.php');
require_once('php_batch_uploader/upload.inc.php');

/* Validate Array Mutate
$t[0]["first"]="John";
$t[0]["last"] ="Smith";
$t[1]["first"]="Jane";
$t[1]["last"] ="Doe";

print_r($t);
print_r($t=arrayMutate($t));
print_r($t=arrayMutate($t));
*/
$c=72;
$j=0;
for ($i=0;($i)<$c;$i+=10) {
	for ($j=0;($i+$j)<$c&&$j<10;$j++) {
		$k=$j+$i;
		list(makeThumbBatch
		echo $k."\n";
	}
}
/*
helloCounter();
helloCounter();
helloCounter();
helloCounter();
function helloCounter() {
	static $a = 0;
	$a++;
	var_dump($a);
}
*/