<?php


neq(true,false);

neq(false,true);


neq('A','a');

$obj1 = (object)array('a'=>1,'b'=>2,'c'=>3);
$obj2 = (object)array('a'=>1,'b'=>2);
neq($obj1,$obj2);


$xml = new \testman\Xml('abc','ABCDEFG');
neq('XYZ',$xml);


