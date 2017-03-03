<?php
eq(1,1);

eq(true,true);

eq(false,false);


eq('A','A');

$obj1 = (object)['a'=>1,'b'=>2];
$obj2 = (object)['a'=>1,'b'=>2];
eq($obj1,$obj2);

$xml = new \testman\Xml('abc','ABCDEFG');
eq('ABCDEFG',$xml);


eq(true);
