<?php

$cwd = getcwd();

include_once(__DIR__.'/bootstrap.php');
include_once(__DIR__.'/test/testman.phar');

print(json_encode(array(
	'a'=>$cwd,
	'b'=>getcwd(),
)));


