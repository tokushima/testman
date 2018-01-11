<?php
$b = b();

$b->do_post(['select','inc']);
eq(200,$b->status());
eq($b->json('a'),$b->json('b'));

