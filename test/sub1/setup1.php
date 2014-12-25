<?php
/**
 * 上位階層のあとに__setup__が実行される
 */

eq('AAA',$var_a);
eq('BBB',$var_b);
eq('ABCDEF',file_get_contents($newdata_file));

