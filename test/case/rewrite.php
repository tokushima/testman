<?php

$b = b();
$b->do_get('http://localhost:8000/cookie2.php');

eq('http://localhost:8000/cookie.php', $b->url());

