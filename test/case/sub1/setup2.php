<?php
/**
 * __teardown__追加されるGHIはつかないはず
 */

eq('XXX',$var_a); // ./__setup__.phpで定義されていないので上書かれない
eq('BBB',$var_b);

eq('ABCDEF',file_get_contents($newdata_file));
