<?php
$b = b();

$b->do_get(url('select','xml'));
eq(200,$b->status());
eq('<abc>123</abc>',$b->xml('result'));

