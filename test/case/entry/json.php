<?php
$b = b();

$b->do_get(url('select','json'));
eq(200,$b->status());
eq(['abc'=>123],$b->json('result'));

