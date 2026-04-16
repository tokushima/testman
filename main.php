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
if(!function_exists('img_eq')){
	/**
	 * 画像ファイルが等しいことを検証する（PNG, JPG, PDF対応）
	 *
	 * アンチエイリアス差はエッジ検出で自動判定し無視する
	 *
	 * @param string $expected_file 期待画像のパス
	 * @param string $actual_file 実際の画像のパス
	 * @param bool $ignore_antialiasing アンチエイリアス差を無視するか
	 * @param int $page PDFの場合のページ番号（0始まり）
	 * @param string $msg 失敗時のメッセージ
	 * @throws \testman\AssertFailure
	 */
	function img_eq(string $expected_file, string $actual_file, bool $ignore_antialiasing = true, int $page = 0, string $msg = 'failure image equals'): void{
		$result = \testman\ImageComparator::compare($expected_file, $actual_file, $ignore_antialiasing, $page);
		if($result['diff_ratio'] > 0.0){
			$a = $result['analysis'];
			$detail = sprintf(
				'diff=%.2f%% (edge:%d solid:%d maxChDiff:%d)',
				($a['total_diff_pixels'] / max($a['total_pixels'], 1)) * 100,
				$a['edge_pixels'],
				$a['solid_pixels'],
				$a['max_channel_diff']
			);
			throw (new \testman\AssertFailure($msg))->ab('images match', $detail);
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
if(sizeof($debug) > 1 || (isset($debug[0]['file']) && substr($debug[0]['file'], -5) != '.phar' && empty(\Phar::running()))){
	return;
}

// CLI実行
\testman\Args::init();

// 未知のオプションチェック
$unknown = \testman\Args::unknown_opts(['stub', 'install', 'help', 'list', 'l', 'info', 'i', 'I', 'parallel', 'p', 'version']);
if(!empty($unknown)){
	fwrite(STDERR, 'Unknown option: --'.implode(', --', $unknown).PHP_EOL);
	fwrite(STDERR, 'Run with --help for usage information'.PHP_EOL);
	exit(1);
}

// --stub: バナーなしで stubs を stdout に出力して終了
if(\testman\Args::opt('stub')){
	$stubs_path = \Phar::running() ? \Phar::running().'/src/stubs.php' : __DIR__.'/src/stubs.php';
	if(!is_file($stubs_path)){
		fwrite(STDERR, 'stubs.php not found: '.$stubs_path.PHP_EOL);
		exit(1);
	}
	echo file_get_contents($stubs_path);
	exit(0);
}

// --install: /usr/local/bin/testman にインストール
if(\testman\Args::opt('install')){
	$phar_path = \Phar::running(false);
	if(empty($phar_path)){
		fwrite(STDERR, 'Error: --install can only be used from testman.phar'.PHP_EOL);
		exit(1);
	}
	$install_path = \testman\Args::opt('install');
	if(!is_string($install_path)){
		$install_path = '/usr/local/bin/testman';
	}

	// pharを直接コピー（composer方式）
	if(!@copy($phar_path, $install_path)){
		fwrite(STDERR, 'Error: Permission denied. Try: sudo php '.$phar_path.' --install'.PHP_EOL);
		exit(1);
	}
	chmod($install_path, 0755);
	echo 'Installed to '.$install_path.PHP_EOL;
	exit(0);
}

$testdir = realpath(\testman\Args::value(getcwd().'/test'));

if($testdir === false){
	\testman\Std::println_danger(\testman\Args::value().' not found');
	exit(1);
}

// グローバルインストール時の設定ファイル探索基準をテストディレクトリに設定
\testman\Conf::set_base_dir(is_file($testdir) ? dirname($testdir) : $testdir);

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

$version = trim((string)@file_get_contents(__DIR__.'/version'));

if(\testman\Args::opt('version')){
	echo 'testman '.($version ?: 'unknown').PHP_EOL;
	exit(0);
}

testman\Std::println('testman '.($version ? $version.' ' : '').'(PHP '.phpversion().')');
testman\Std::println();

// コマンド処理
if(\testman\Args::opt('help')){
	\testman\Std::println_info('Usage: php '.basename(__FILE__).' [options] [dir/ | dir/file.php]');
	\testman\Std::println();
	\testman\Std::println_primary('Options:');
	\testman\Std::println();
	\testman\Std::println('  --list [keyword]  List test files');
	\testman\Std::println('  --info            Info setup[s]');
	\testman\Std::println('  -p, --parallel N  Run tests in parallel (N workers, default: CPU cores)');
	\testman\Std::println('  --stub      Dump IDE stubs to stdout');
	\testman\Std::println('  --install [path]  Install to /usr/local/bin/testman (or specified path)');
	\testman\Std::println('  --version         Show version');
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
	}catch(\Throwable $e){
		\testman\Std::println_danger(PHP_EOL.get_class($e).': '.$e->getMessage().PHP_EOL.PHP_EOL.$e->getTraceAsString());
		exit(1);
	}
}
