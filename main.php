<?php
if(!function_exists('fail')){
	/**
	 * 失敗とする
	 * @param string $msg 失敗時メッセージ
	 * @throws \testman\AssertFailure
	 */
	function fail(string $msg='failure'){
		throw new \testman\AssertFailure($msg);
	}
}
if(!function_exists('eq')){
	/**
	 *　等しい
	 * @param mixed $expectation 期待値
	 * @param mixed $result 実行結果
	 * @param string $msg 失敗時メッセージ
	 */
	function eq($expectation,$result=null,$msg='failure equals'){
		if(func_num_args() == 1){
			$result = $expectation;
			$expectation = true;
		}
		if(($result instanceof \testman\Xml) && !($expectation instanceof \testman\Xml)){
			$result = $result->value();
		}
		if(\testman\Assert::expvar($expectation) !== \testman\Assert::expvar($result)){
			$failure = new \testman\AssertFailure($msg);
			throw $failure->ab($expectation, $result);
		}
	}
}
if(!function_exists('neq')){
	/**
	 * 等しくない
	 * @param mixed $expectation 期待値
	 * @param mixed $result 実行結果
	 * @param string $msg 失敗時メッセージ
	 */
	function neq($expectation,$result,$msg='failure not equals'){
		if(($result instanceof \testman\Xml) && !($expectation instanceof \testman\Xml)){
			$result = $result->value();
		}
		if(\testman\Assert::expvar($expectation) === \testman\Assert::expvar($result)){
			$failure = new \testman\AssertFailure($msg);
			throw $failure->ab($expectation, $result);
		}
	}
}
if(!function_exists('meq')){
	/**
	 *　文字列中に指定の文字列が存在する
	 * @param string|array $keyword
	 * @param string $result
	 * @param string $msg 失敗時メッセージ
	 */
	function meq($keyword,$result,$msg='failure match'){
		if(($result instanceof \testman\Xml)){
			$result = $result->value();
		}
		if(mb_strpos($result,$keyword) === false){
			$failure = new \testman\AssertFailure($msg);
			if(strlen($result) > 80){
				$result = substr($result,0,80).' ...';
			}
			throw $failure->ab($keyword,$result);
		}
	}
}
if(!function_exists('mneq')){
	/**
	 * 文字列中に指定の文字列が存在しない
	 * @param string $keyword
	 * @param string $src
	 */
	function mneq($keyword,$result,$msg='failure not match'){
		if(($result instanceof \testman\Xml)){
			$result = $result->value();
		}
		if(mb_strpos($result,$keyword) !== false){
			$failure = new \testman\AssertFailure($msg);
			if(strlen($result) > 80){
				$result = substr($result,0,80).' ...';
			}
			throw $failure->ab($keyword,$result);
		}
	}
}

if(!function_exists('b')){
	function b(): \testman\Browser{
		return new \testman\Browser();
	}
}


// include
$debug = debug_backtrace(false);
if(sizeof($debug) > 1 || (isset($debug[0]['file']) && substr($debug[0]['file'],-5) != '.phar')){
	return;
}

\testman\Args::init();
$testdir = realpath(\testman\Args::value(getcwd().'/test'));
if($testdir === false){
	\testman\Std::println_danger(\testman\Args::value().' not found');
	exit;
}
if(is_file($f=getcwd().'/bootstrap.php') || is_file($f=getcwd().'/vendor/autoload.php')){
	ob_start();
		include_once($f);
	ob_end_clean();
}

\testman\Std::println('testman (PHP '.phpversion().')');
\testman\Std::println();

if(\testman\Args::opt('help')){
	\testman\Std::println_info('Usage: php '.basename(__FILE__).' [options] [dir/ | dir/file.php]');
	\testman\Std::println();
	\testman\Std::println_primary('Options:');
	\testman\Std::println();
	\testman\Std::println('  --list [keyword]  List test files');
	\testman\Std::println('  --info            Info setup[s]');
	\testman\Std::println();
}else if(($keyword = \testman\Args::opt('list',false)) !== false || ($keyword = \testman\Args::opt('l',false)) !== false){
	\testman\Finder::summary_list($testdir,$keyword);
}else if(($p=\testman\Args::opt('info',false)) !== false || ($p=\testman\Args::opt('i',false)) !== false){
	if($p === true){
		$p = \testman\Args::value();
		
		if(empty($p)){
			$p = is_dir(getcwd().'/test') ? getcwd().'/test' : getcwd();
		}
	}
	\testman\Finder::setup_info($p);
}else{
	try{
		\testman\Runner::start($testdir);
	}catch(\Exception $e){
		\testman\Std::println_danger(PHP_EOL.get_class($e).': '.$e->getMessage().PHP_EOL.PHP_EOL.$e->getTraceAsString());
	}
}

