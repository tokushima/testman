<?php
$src = <<< _SRC
	aaaaa
	bbbbb
	ccccc
	ddddd
_SRC;


meq('aaa',$src);
meq('ccc',$src);


$xml = new \testman\Xml('abc','ABCDEFG');
meq('DEF',$xml);
