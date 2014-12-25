<?php
/**
 * @var string $newdata_file
 * @var string $var_a
 */
$newdata_file = getcwd().'/newdata.dat';

file_put_contents($newdata_file,'ABC');


$var_a = 'XXX';
