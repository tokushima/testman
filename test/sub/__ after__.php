<?php
$file = getcwd().'/newdata.dat';
file_put_contents($file,file_get_contents($file).'GHI');

