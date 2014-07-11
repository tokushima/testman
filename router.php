<?php
/**
 * PHP Built in server rewrite rule router.php
 * 
 * RewriteEngine On
 * RewriteBase /
 * 
 * RewriteCond %{REQUEST_FILENAME} !-f
 * RewriteCond %{REQUEST_FILENAME} !-d
 * RewriteRule ^abc[/]{0,1}(.*)$ abc.php/$1?%{QUERY_STRING} [L]
 * 
 * RewriteCond %{REQUEST_FILENAME} !-f
 * RewriteRule ^(.*)$ index.php/$1?%{QUERY_STRING} [L]
 */

$dir = getcwd();
$uri = $_SERVER['REQUEST_URI'];

if(!is_file($dir.$uri)){
	if(strpos($uri,'?') !== false){
		list($uri) = explode('?',$uri,2);
	}
	if(substr($uri,0,1) == '/'){
		$uri = substr($uri,1);
	}	
	$exp = explode('/',$uri,2);
	$entry = $exp[0];
	$path = (isset($exp[1])) ? $exp[1] : '';
	
	if(empty($entry)){
		$entry = 'index';
	}
	if(is_file($f=$dir.'/'.$entry.'.php')){
		$_SERVER['PATH_INFO'] = '/'.$path;
		
		file_put_contents('php://stdout',date('[D M d H:i:s Y] ').basename($f).' [---]: '.$_SERVER['REQUEST_URI'].PHP_EOL);
		include($f);		
		return false;
	}
}
return false;