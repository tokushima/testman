<?php

$xml = new \testman\Xml("test");
eq("hoge",$xml->value("hoge"));
eq("true",$xml->value(true));
eq("false",$xml->value(false));
eq("<abc>1</abc><def>2</def><ghi>3</ghi>",$xml->value(["abc"=>1,"def"=>2,"ghi"=>3]));
eq(null,$xml->value(''));
eq(1,$xml->value('1'));
eq(null,$xml->value(null));
$xml->escape(true);
eq("<abc>123</abc>",$xml->value("<abc>123</abc>"));
eq("<b>123</b>",$xml->value(new \testman\Xml("b","123")));

$xml = new \testman\Xml("test");
$xml->escape(false);
eq("<abc>123</abc>",$xml->value("<abc>123</abc>",false));


$xml = new \testman\Xml("test");
$add = new \testman\Xml("addxml","hoge");
$xml->add($add);
$xml->add($add->get());
$xml->add((string)$add);
eq('<test><addxml>hoge</addxml><![CDATA[<addxml>hoge</addxml>]]><![CDATA[<addxml>hoge</addxml>]]></test>',$xml->get());


$xml = new \testman\Xml("test");
$add = new \testman\Xml("addxml","hoge");
$xml->add($add);
$xml->add($add->get());
$xml->add((string)$add);
eq('<test><addxml>hoge</addxml><![CDATA[<addxml>hoge</addxml>]]><![CDATA[<addxml>hoge</addxml>]]></test>',$xml->get());
