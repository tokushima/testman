<?php
const TESTMAN_MSG_TRUNCATE_LEN = 80;

if(!function_exists('_testman_resolve_value')){
	/**
	 * Xmlインスタンスの場合は値を取得する
	 * @param mixed $value
	 * @param mixed $compare 比較対象（Xmlでない場合のみ変換）
	 * @return mixed
	 */
	function _testman_resolve_value($value, $compare = null){
		if(($value instanceof \testman\Xml) && !($compare instanceof \testman\Xml)){
			return $value->value();
		}
		return $value;
	}
}
if(!function_exists('_testman_truncate')){
	/**
	 * 長い文字列を省略する
	 */
	function _testman_truncate(string $str, int $len = TESTMAN_MSG_TRUNCATE_LEN): string{
		return (strlen($str) > $len) ? substr($str, 0, $len).' ...' : $str;
	}
}
if(!function_exists('fail')){
	/**
	 * 失敗とする
	 * @throws \testman\AssertFailure
	 */
	function fail(string $msg = 'failure'): void{
		throw new \testman\AssertFailure($msg);
	}
}
if(!function_exists('eq')){
	/**
	 * 等しい
	 * @param mixed $expectation 期待値
	 * @param mixed $result 実行結果
	 * @throws \testman\AssertFailure
	 */
	function eq($expectation, $result = null, string $msg = 'failure equals'): void{
		if(func_num_args() == 1){
			$result = $expectation;
			$expectation = true;
		}
		$result = _testman_resolve_value($result, $expectation);

		if(\testman\Assert::expvar($expectation) !== \testman\Assert::expvar($result)){
			throw (new \testman\AssertFailure($msg))->ab($expectation, $result);
		}
	}
}
if(!function_exists('neq')){
	/**
	 * 等しくない
	 * @param mixed $expectation 期待値
	 * @param mixed $result 実行結果
	 * @throws \testman\AssertFailure
	 */
	function neq($expectation, $result, string $msg = 'failure not equals'): void{
		$result = _testman_resolve_value($result, $expectation);

		if(\testman\Assert::expvar($expectation) === \testman\Assert::expvar($result)){
			throw (new \testman\AssertFailure($msg))->ab($expectation, $result);
		}
	}
}
if(!function_exists('meq')){
	/**
	 * 文字列中に指定の文字列が存在する
	 * @param string $keyword
	 * @param string|\testman\Xml $result
	 * @throws \testman\AssertFailure
	 */
	function meq(string $keyword, $result, string $msg = 'failure match'): void{
		$result = _testman_resolve_value($result);

		if(mb_strpos($result, $keyword) === false){
			throw (new \testman\AssertFailure($msg))->ab($keyword, _testman_truncate($result));
		}
	}
}
if(!function_exists('mneq')){
	/**
	 * 文字列中に指定の文字列が存在しない
	 * @param string $keyword
	 * @param string|\testman\Xml $result
	 * @throws \testman\AssertFailure
	 */
	function mneq(string $keyword, $result, string $msg = 'failure not match'): void{
		$result = _testman_resolve_value($result);

		if(mb_strpos($result, $keyword) !== false){
			throw (new \testman\AssertFailure($msg))->ab($keyword, _testman_truncate($result));
		}
	}
}
if(!function_exists('b')){
	/**
	 * Browserインスタンスを生成
	 */
	function b(): \testman\Browser{
		return new \testman\Browser();
	}
}


// ライブラリとしてincludeされた場合は関数定義のみで終了
$debug = debug_backtrace(false);
if(sizeof($debug) > 1 || (isset($debug[0]['file']) && substr($debug[0]['file'], -5) != '.phar')){
	return;
}

// CLI実行
\testman\Args::init();
$testdir = realpath(\testman\Args::value(getcwd().'/test'));

if($testdir === false){
	\testman\Std::println_danger(\testman\Args::value().' not found');
	exit(1);
}

// bootstrap/autoload読み込み
$bootstrap_files = [getcwd().'/bootstrap.php', getcwd().'/vendor/autoload.php'];
foreach($bootstrap_files as $f){
	if(is_file($f)){
		ob_start();
		include_once($f);
		ob_end_clean();
		break;
	}
}

\testman\Std::println('testman (PHP '.phpversion().')');
\testman\Std::println();

// コマンド処理
if(\testman\Args::opt('help')){
	\testman\Std::println_info('Usage: php '.basename(__FILE__).' [options] [dir/ | dir/file.php]');
	\testman\Std::println();
	\testman\Std::println_primary('Options:');
	\testman\Std::println();
	\testman\Std::println('  --list [keyword]  List test files');
	\testman\Std::println('  --info            Info setup[s]');
	\testman\Std::println('  -p, --parallel N  Run tests in parallel (N workers, default: CPU cores)');
	\testman\Std::println();
}else if(($keyword = \testman\Args::opt('list', false)) !== false || ($keyword = \testman\Args::opt('l', false)) !== false){
	\testman\Finder::summary_list($testdir, $keyword);
}else if(($p = \testman\Args::opt('info', false)) !== false || ($p = \testman\Args::opt('i', false)) !== false || ($p = \testman\Args::opt('I', false)) !== false){
	if($p === true){
		$p = \testman\Args::value() ?: (is_dir(getcwd().'/test') ? getcwd().'/test' : getcwd());
	}
	\testman\Finder::setup_info($p, \testman\Args::opt('I', false) !== false);
}else{
	try{
		// 並列実行オプション
		$parallel = \testman\Args::opt('parallel', false);
		if($parallel === false){
			$parallel = \testman\Args::opt('p', false);
		}
		$results = \testman\Runner::start($testdir, $parallel);

		// 中断された場合は終了コード130（SIGINT）
		if(\testman\Runner::is_interrupted()){
			exit(130);
		}

		// 失敗がある場合は終了コード1
		foreach($results as $res){
			if($res[0] !== 1){
				exit(1);
			}
		}
	}catch(\Exception $e){
		\testman\Std::println_danger(PHP_EOL.get_class($e).': '.$e->getMessage().PHP_EOL.PHP_EOL.$e->getTraceAsString());
		exit(1);
	}
}
