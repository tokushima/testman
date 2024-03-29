<?php
// in_attr
$x = new \testman\Xml("test");
$x->attr("abc",123);
eq("123",$x->in_attr("abc"));
eq(null,$x->in_attr("def"));
eq("456",$x->in_attr("ghi",456));

$x->attr("def","'<>'");

$x->escape(true);
eq("'<>'",$x->in_attr("def"));
eq('<test abc="123" def="&#039;&lt;&gt;&#039;" />',$x->get());

$x->escape(false);
eq("'<>'",$x->in_attr("def"));
eq('<test abc="123" def="\'<>\'" />',$x->get());


// rm
$x = new \testman\Xml("test");
$x->attr("abc",123);
$x->attr("def",456);
$x->attr("ghi",789);

eq(["abc"=>123,"def"=>456,"ghi"=>789],iterator_to_array($x));
$x->rm_attr("def");
eq(["abc"=>123,"ghi"=>789],iterator_to_array($x));
$x->attr("def",456);
eq(["abc"=>123,"ghi"=>789,"def"=>456],iterator_to_array($x));
$x->rm_attr("abc","ghi");
eq(["def"=>456],iterator_to_array($x));


// is 
$x = new \testman\Xml("test");
eq(false,$x->is_attr("abc"));
$x->attr("abc",123);
eq(true,$x->is_attr("abc"));
$x->attr("abc",null);
eq(true,$x->is_attr("abc"));
$x->rm_attr("abc");
eq(false,$x->is_attr("abc"));

//set
$x = new \testman\Xml("test");
$x->escape(true);
$x->attr("abc",123);
eq(123,$x->in_attr("abc"));
$x->attr("Abc",456);
eq(456,$x->in_attr("abc"));
$x->attr("DEf",555);
eq(555,$x->in_attr("def"));
eq(456,$x->in_attr("abc"));
$x->attr("Abc","<aaa>");
eq("<aaa>",$x->in_attr("abc"));
$x->attr("Abc",'true');
eq("true",$x->in_attr("abc"));
$x->attr("Abc",'false');
eq("false",$x->in_attr("abc"));
$x->attr("Abc",null);
eq(null,$x->in_attr("abc"));
$x->attr("ghi",null);
eq(null,$x->in_attr("ghi"));
eq(["abc"=>null,"def"=>555,"ghi"=>null],iterator_to_array($x));

$x->attr("Jkl","Jkl");
eq(["abc"=>null,"def"=>555,"ghi"=>null,"jkl"=>"Jkl"],iterator_to_array($x));
