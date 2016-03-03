<?php
$b = b();

$b->do_get(url('select','inc'));
eq(200,$b->status());
eq('abcdef',$b->body());


