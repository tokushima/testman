<?php
$x = new \testman\Xml("test");
$x->value("abc");
eq("abc",$x->value());
$x->add("def");
eq("abcdef",$x->value());
$x->add(new \testman\Xml("b","123"));
eq("abcdef<b>123</b>",$x->value());
