<?php
/**
 * __teardown__追加されるGHIはつかないはず
 */

eq('AAA',$var_a);
eq('BBB',$var_b);

eq('ABCDEF',file_get_contents($newdata_file));
