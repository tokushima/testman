<?php
$b = b();

$b->do_get(['select','xml']);
eq(200,$b->status());
eq('<abc>123</abc>',$b->xml('result'));

