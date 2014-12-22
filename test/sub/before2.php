<?php
/**
 * __after__追加されるGHIはつかないはず
 */
eq('ABCDEF',file_get_contents(getcwd().'/newdata.dat'));
