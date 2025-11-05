<?php
ini_set('display_errors','On');
ini_set('html_errors','Off');
ini_set('error_reporting',E_ALL);
ini_set('xdebug.overload_var_dump',0);
ini_set('xdebug.var_display_max_children',-1);
ini_set('xdebug.var_display_max_data',-1);
ini_set('xdebug.var_display_max_depth',-1);
ini_set('memory_limit',-1);

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


