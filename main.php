<?php
// coverage client
if(php_sapi_name() !== 'cli'){
	$linkkey = \testman\Coverage::link();

	if(isset($_POST[$linkkey]) || isset($_GET[$linkkey])){
		$linkvars = isset($_POST[$linkkey]) ? $_POST[$linkkey] : (isset($_GET[$linkkey]) ? $_GET[$linkkey] : []);
		if(isset($_POST[$linkkey])){
			unset($_POST[$linkkey]);
		}
		if(isset($_GET[$linkkey])){
			unset($_GET[$linkkey]);
		}
		if(function_exists('xdebug_get_code_coverage') && isset($linkvars['coverage_data_file'])){
			register_shutdown_function(function() use($linkvars){
				register_shutdown_function(function() use($linkvars){
					$db = $linkvars['coverage_data_file'];
					$target_list_db = $db.'.target';
					$tmp_db = $db.'.tmp';

					if(is_file($target_list_db)){
						$target_list = explode(PHP_EOL,file_get_contents($target_list_db));
						$fp = fopen($tmp_db,'a');

						foreach(xdebug_get_code_coverage() as $file_path => $lines){
							if(false !== ($i = array_search($file_path,$target_list))){
								fwrite($fp,json_encode([$i,$lines]).PHP_EOL);
							}
						}
						fclose($fp);
					}
					xdebug_stop_code_coverage();
				});
			});
			xdebug_start_code_coverage();
		}
	}
	return;
}



// set functions
if(!function_exists('conf_urls')){
	/**
	 * mapにurlを定義する
	 * @param array $urls
	 */
	function conf_urls(array $urls){
		\testman\Conf::set('urls',$urls);
	}
}
if(!function_exists('fail')){
	/**
	 * 失敗とする
	 * @param string $msg 失敗時メッセージ
	 * @throws \testman\AssertFailure
	 */
	function fail($msg='failure'){
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

if(!function_exists('url')){
	/**
	 * mapに定義されたurlをフォーマットして返す
	 * @param string $map_name
	 * @throws \RuntimeException
	 * @return string
	 */
	function url($map_name){
		$args = func_get_args();
		array_shift($args);
		$urls = \testman\Conf::get('urls',[]);

		if(empty($ruls) && class_exists('\ebi\Dt')){
			$urls = \ebi\Dt::get_urls();
		}
		if(empty($urls) || !is_array($urls)){
			throw new \testman\NotFoundException('urls empty');
		}
		if(isset($urls[$map_name]) && substr_count($urls[$map_name],'%s') == sizeof($args)){
			return vsprintf($urls[$map_name],$args);
		}
		throw new \testman\NotFoundException($map_name.(isset($urls[$map_name]) ? '['.sizeof($args).']' : '').' not found');
	}
}
if(!function_exists('b')){
	/**
	 * ブラウザを返す
	 * @return \testman\Browser
	 */
	function b(){
		return new \testman\Browser();
	}
}
/**
 * テスト用リソースファイルのパスを取得する
 */
if(!function_exists('resource')){
	function resource($file){
		return \testman\Resource::path($file);
	}
}
if(!function_exists('rand_id')){
	/**
	 * ランダムなID を生成する
	 * @return  string
	 */
	function rand_id($id,$length=32){
		$code = '';
			
		for($i=0;$i<=$length;$i+=32){
			$code .= md5(base64_encode($id.microtime().rand(1,9999999)));
		}
		return substr($code,$length*-1);
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

if(\testman\Args::has_opt('coverage')){
	$v = \testman\Args::opt('coverage');
	\testman\Conf::set('coverage',(is_bool($v) ? 'coverage.xml' : $v));
}
if(($v = \testman\Args::opt('output')) !== null && !is_bool($v)){
	\testman\Conf::set('output',$v);
}
\testman\Std::println('testman (PHP '.phpversion().')');
\testman\Std::println();

if(\testman\Args::opt('help')){
	\testman\Std::println_info('Usage: php '.basename(__FILE__).' [options] [dir/ | dir/file.php]');
	\testman\Std::println();
	\testman\Std::println_primary('Options:');
	\testman\Std::println('  --coverage <coverage file> Generate code coverage report in XML format');
	\testman\Std::println('  --benchmark <benchmark file> Generate benchmark report');
	\testman\Std::println('  --output <file>   Log test execution in XML format to file');
	\testman\Std::println('  --nobs            Disabeld Std.bs(back space print)');
	\testman\Std::println();
	\testman\Std::println('  --list [keyword]  List test files');
	\testman\Std::println('  --info            Info setup[s]');
	\testman\Std::println('  --init            Create init files');
	\testman\Std::println('  --coverd <coverage file> View coverage report');
	\testman\Std::println();
}else if(($keyword = \testman\Args::opt('list',false)) !== false || ($keyword = \testman\Args::opt('l',false)) !== false){
	\testman\Finder::summary_list($testdir,$keyword);
}else if((\testman\Args::opt('init',false)) !== false){
	$newfile = function($file,$source){
		$fp = \testman\Conf::settings_path($file);
			
		if(!is_file($fp)){
			file_put_contents($fp,'<?php'.PHP_EOL.$source);
			\testman\Std::println_info('Written '.$fp);
		}
	};
	$newfile('testman.settings.php', <<< '_SRC_'
// テスト用の設定をします
// \testman\Conf::set('urls',\ebi\Dt::get_urls());
// \testman\Conf::set('output',dirname(__DIR__).'/work/result.xml');
// \testman\Conf::set('ssl-verify',false);
_SRC_
	);
	$newfile('testman.fixture.php',<<< '_SRC_'
// テスト用の初期データを作成します
// \ebi\Dt::setup();
_SRC_
	);
	$newfile('__setup__.php',<<< '_SRC_'
// テスト毎の開始処理
// \ebi\Exceptions::clear();
_SRC_
	);
	$newfile('__teardown__.php',<<< '_SRC_'
// テスト毎の終了処理

_SRC_
	);

	if(!is_dir($dir=\testman\Conf::settings_path('testman.lib'))){
		mkdir($dir,0755,true);
		\testman\Std::println_info('Create '.$dir);
		
		$newfile('testman.lib/Util.php',<<< '_SRC_'
namespace test; // namespaceはtestから始まる

class Util{
}
_SRC_
		);
	}
}else if(($p=\testman\Args::opt('info',false)) !== false || ($p=\testman\Args::opt('i',false)) !== false){
	if($p === true){
		$p = \testman\Args::value();
		
		if(empty($p)){
			$p = is_dir(getcwd().'/test') ? getcwd().'/test' : getcwd();
		}
	}
	\testman\Finder::setup_info($p);
}else if(($covered_file = \testman\Args::opt('covered',false)) !== false){
	try{
		$create_date = \testman\Coverage::load($covered_file);
		$source = \testman\Args::opt('source');

		if(is_file($source)){
			\testman\Coverage::output_source($source,$create_date);
		}else{
			\testman\Coverage::output(false);
		}
	}catch(\Exception $e){
		\testman\Std::println_danger($e->getMessage());
	}
}else{
	try{
		\testman\Conf::set('stdbs',!\testman\Args::opt('nobs',false));
		\testman\Runner::start($testdir);

		$save_path = null;
		
		if(\testman\Args::has_opt('benchmark')){
			$save_path = \testman\Args::opt('benchmark');
		}
		if(empty($save_path)){
			$save_path = \testman\Conf::get('benchmark');
		}
		if(!empty($save_path)){
			if(!is_dir(basename($save_path))){
				if(!mkdir(basename($save_path))){
					throw new \InvalidArgumentException('Creation of benchmark file failed');
				}
			}
			if(is_file($save_path)){
				unlink($save_path);
			}
			if(!file_put_contents($save_path,sprintf("%s\t%s\t%s\t%s".PHP_EOL,'Path','Time','Mem','Peak Mem'))){
				throw new \InvalidArgumentException('Creation of benchmark file failed');
			}
			foreach(\testman\Runner::benchmark() as $name => $values){
				$line = implode("\t",array_merge([$name],$values));
				file_put_contents($save_path,$line.PHP_EOL,FILE_APPEND);
			}
			\testman\Std::println_primary(' Written Benchmark: '.$save_path);
		}
	}catch(\Exception $e){
		\testman\Std::println_danger(PHP_EOL.get_class($e).': '.$e->getMessage().PHP_EOL.PHP_EOL.$e->getTraceAsString());
	}
}

