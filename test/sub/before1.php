<?php
/**
 * 上位階層のあとに__before__が実行される
 */
eq('ABCDEF',file_get_contents(getcwd().'/newdata.dat'));
