<?php
$b = b();

$b->do_get(url('select','inc'));
eq(200,$b->status());
eq('abcdefGET',$b->body());

$b->do_post(url('select','inc'));
eq(200,$b->status());
eq('abcdefPOST',$b->body());

