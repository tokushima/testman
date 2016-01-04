<?php
$src = <<< _SRC
	aaaaa
	bbbbb
	ccccc
	ddddd
_SRC;


mneq('xxx',$src);
mneq('yyy',$src);

$xml = new \testman\Xml('abc','ABCDEFG');
mneq('XYZ',$xml);
