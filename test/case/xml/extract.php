<?php
$p = "<abc><def>111</def></abc>";
$x = \testman\Xml::extract($p,'abc');
eq("abc",$x->name());

$p = "<abc><def>111</def></abc>";
$x = \testman\Xml::extract($p,"def");
eq("def",$x->name());
eq(111,$x->value());

try{
	$p = "aaaa";
	\testman\Xml::extract($p,'abc');
	failure();
}catch(\testman\NotFoundException $e){
}

try{
	$p = "<abc>sss</abc>";
	\testman\Xml::extract($p,"def");
	failure();
}catch(\testman\NotFoundException $e){
}

$p = "<abc>sss</a>";
$x = \testman\Xml::extract($p,'abc');
eq("<abc />",$x->get());

$p = "<abc>0</abc>";
$x = \testman\Xml::extract($p,'abc');
eq("abc",$x->name());
eq("0",$x->value());


