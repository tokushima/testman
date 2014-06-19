<?php
/**
 *  php -d phar.readonly=0 **.php
 */
$filename = substr(basename(__FILE__),0,strpos(basename(__FILE__),'.'));
$output = __DIR__.'/'.$filename.'.phar';

if(is_file($output)){
	unlink($output);
}
try{
	$phar = new Phar($output,0,$filename.'.phar');
	$phar[$filename.'.php'] = file_get_contents(__DIR__.'/'.$filename.'.php');
	$stab = <<< 'STAB'
<?php
		Phar::mapPhar('%s.phar');
		include 'phar://%s.phar/%s.php';
		__HALT_COMPILER();
?>
STAB;
	$phar->setStub(sprintf($stab,$filename,$filename,$filename));
	$phar->compressFiles(Phar::GZ);
	
	if(is_file($output)){
		print('Created '.$output.' ['.filesize($output).' byte]'.PHP_EOL);
	}else{
		print('Failed '.$output.PHP_EOL);
	}
}catch (Exception $e){
	var_dump($e->getMessage());
	var_dump($e->getTraceAsString());
}
