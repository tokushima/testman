<?php
\testman\Conf::set([
	'urls'=>[
		'select'=>'http://localhost:8000/entry_%s.php',
		'cookie'=>'http://localhost:8000/cookie.php',
		'cookie_result'=>'http://localhost:8000/cookie_result.php',		
	],
	'url_rewrite'=>[
		'/http:\/\/localhost:8000\/cookie2\.php/'=>'cookie',
	],
]);

