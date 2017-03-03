<?php

$cwd = getcwd();

print(json_encode(array(
	'a'=>$cwd,
	'b'=>getcwd(),
)));


