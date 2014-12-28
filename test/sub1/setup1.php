<?php
/**
 * 上位階層のあとに__setup__が実行される
 */

eq('XXX',$var_a); // ./__setup__.phpで定義されていないので上書かれない
eq('BBB',$var_b);
eq('ABCDEF',file_get_contents($newdata_file));

