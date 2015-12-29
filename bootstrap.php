<?php
set_include_path(get_include_path()
.PATH_SEPARATOR.__DIR__.'/lib'
);
spl_autoload_register(function($c){
	$cp = str_replace('\\','//',(($c[0] == '\\') ? substr($c,1) : $c));
	
	foreach(explode(PATH_SEPARATOR,get_include_path()) as $p){
		if(!empty($p) && ($r = realpath($p)) !== false && is_file($f=($r.'/'.$cp.'.php'))){
			require_once($f);
			break;
		}
	}
	return (class_exists($c,false) || interface_exists($c,false) || (function_exists('trait_exists') && trait_exists($c,false)));
},true,false);


