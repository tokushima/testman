<?php
/**
 * @var string $newdata_file 変数FILE
 * @var string $var_a 変数A1
 */
$newdata_file = getcwd().'/newdata.dat';

file_put_contents($newdata_file,'ABC');


$var_a = 'XXX';
