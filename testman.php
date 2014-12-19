<?php
/**
 * Testing Framework
 * PHP 5 >= 5.3.0 
 * @author tokushima
 */
namespace testman{
	class Conf{
		static private $conf = array();
		
		/**
		 * セット
		 * @param string $k
		 * @param mixed $v
		 */
		public static function set($k,$v){
			self::$conf[$k] = $v;
		}
		/**
		 * ゲット
		 * @param string $name
		 * @param mixed $default
		 * @return mixed
		 */
		public static function get($name,$default=null){
			if(isset(self::$conf[$name])){
				return self::$conf[$name];
			}
			return $default;
		}
		/**
		 * セットされているか
		 * @param string $name
		 * @return boolean
		 */
		public static function has($name){
			return array_key_exists($name,self::$conf);
		}
		/**
		 * 設定ファイル/ディレクトリが存在するか
		 * @param string $name
		 * @return Ambigous <NULL, string>|NULL
		 */				
		public static function has_settings($name){
			$dir = dirname(__FILE__);
			if(strpos($dir,'phar://') === 0){
				$dir = str_replace('phar://','',dirname($dir));
			}
			if(is_dir($dir)){
				$path = $dir.'/testman.'.$name;
				return (is_file($path) || is_dir($path) ? $path : null);
			}
			return null;
		}
	}
	class NotFoundException extends \Exception{
	}
	class AssertFailure extends \Exception{
		private $expectation;
		private $result;
		private $has = false;
		
		public function ab($expectation,$result){
			$this->expectation = $expectation;
			$this->result = $result;
			$this->has = true;
			return $this;
		}
		public function has(){
			return $this->has;
		}
		public function expectation(){
			return $this->expectation;
		}
		public function result(){
			return $this->result;
		}
	}
	class Assert{
		/**
		 * 変数を展開していく
		 * @param mixed $var
		 * @return string
		 */
		public static function expvar($var){
			if(is_numeric($var)){
				return strval($var);
			}
			if(is_object($var)){
				$var = get_object_vars($var);
			}
			if(is_array($var)){
				foreach($var as $key => $v){
					$var[$key] = self::expvar($v);
				}
			}
			return $var;
		}
		/**
		 * 失敗とする
		 * @param string $msg 失敗時メッセージ
		 * @throws \testman\AssertFailure
		 */
		public static function failure($msg='failure'){
			throw new \testman\AssertFailure($msg);
		}
		/**
		 *　等しい
		 * @param mixed $expectation 期待値
		 * @param mixed $result 実行結果
		 * @param string $msg 失敗時メッセージ
		 */
		public static function eq($expectation,$result,$msg='failure equals'){
			if(self::expvar($expectation) !== self::expvar($result)){
				$failure = new \testman\AssertFailure($msg);
				throw $failure->ab($expectation, $result);
			}
		}
		/**
		 * 等しくない
		 * @param mixed $expectation 期待値
		 * @param mixed $result 実行結果
		 * @param string $msg 失敗時メッセージ
		 */
		public static function neq($expectation,$result,$msg='failure not equals'){
			if(self::expvar($expectation) === self::expvar($result)){
				$failure = new \testman\AssertFailure($msg);
				throw $failure->ab($expectation, $result);
			}
		}
		/**
		 *　文字列中に指定の文字列が存在する
		 * @param string|array $keyword
		 * @param string $src
		 * @param string $msg 失敗時メッセージ
		 */
		public static function meq($keyword,$src,$msg='failure match'){
			if(mb_strpos($src,$keyword) === false){
				throw new \testman\AssertFailure($msg);
			}
		}
		/**
		 * 文字列中に指定の文字列が存在しない
		 * @param string $keyword
		 * @param string $src
		 */
		public static function mneq($keyword,$src,$msg='failure not match'){
			if(mb_strpos($src,$keyword) !== false){
				throw new \testman\AssertFailure($msg);
			}
		}
		/**
		 * mapに定義されたurlをフォーマットして返す
		 * @param string $map_name
		 * @throws \RuntimeException
		 * @return string
		 */
		public static function test_map_url($map_name,$args=array()){
			$urls = \testman\Conf::get('urls',array());
			
			if(empty($urls) || !is_array($urls)){
				throw new \testman\NotFoundException('urls empty');
			}	
			if(isset($urls[$map_name]) && substr_count($urls[$map_name],'%s') == sizeof($args)){
				return vsprintf($urls[$map_name],$args);
			}
			throw new \testman\NotFoundException($map_name.(isset($urls[$map_name]) ? '['.sizeof($args).']' : '').' not found');
		}
	}
	class Runner{
		static private $resultset = array();
		static private $start_time;
		static private $current_test;

		/**
		 * 初期化
		 * @param string $testdir
		 * @throws \ErrorException
		 * @throws \RuntimeException
		 * @return boolean
		 */
		public static function init($testdir){
			ini_set('display_errors','On');
			ini_set('html_errors','Off');
			ini_set('error_reporting',E_ALL);
			ini_set('xdebug.var_display_max_children',-1);
			ini_set('xdebug.var_display_max_data',-1);
			ini_set('xdebug.var_display_max_depth',-1);
			ini_set('memory_limit',-1);
			
			if(ini_get('date.timezone') == ''){
				date_default_timezone_set('Asia/Tokyo');
			}
			if(extension_loaded('mbstring')){
				if('neutral' == mb_language()) mb_language('Japanese');
				mb_internal_encoding('UTF-8');
			}
			set_error_handler(function($n,$s,$f,$l){
				throw new \ErrorException($s,0,$n,$f,$l);
			});			
			if(function_exists('opcache_reset')){
				opcache_reset();
			}
			if(null !== ($f = \testman\Conf::has_settings('init.php'))){
				include_once($f);
			}
			if(null !== ($dir = \testman\Conf::has_settings('lib'))){
				if(is_dir($dir) && strpos(get_include_path(),$dir) === false){
					set_include_path($dir.PATH_SEPARATOR.get_include_path());
			
					spl_autoload_register(function($c){
						if(!empty($c)){
							$cp = str_replace('\\','/',(($c[0] == '\\') ? substr($c,1) : $c));
							foreach(explode(PATH_SEPARATOR,get_include_path()) as $p){
								if(!empty($p) && ($r = realpath($p)) !== false && is_file($f=$p.'/'.$cp.'.php')){
									require_once($f);
			
									if(class_exists($c,false) || interface_exists($c,false) || trait_exists($c,false)){
										return true;
									}
								}
							}
						}
						return false;
					},true,false);
				}
			}
			if(null !== ($f = \testman\Conf::has_settings('conf.php'))){
				$conf = include($f);
				if(!is_array($conf)) throw new \RuntimeException('invalid '.$f);
				foreach($conf as $k => $v){
					\testman\Conf::set($k,$v);
				}
			}
		}
		/**
		 * 現在実行しているテスト
		 */
		public static function current(){
			return self::$current_test;
		}
		/**
		 * fixtureを実行する
		 * @return boolean
		 */
		public static function fixture(){
			if(null !== ($f = \testman\Conf::has_settings('fixture.php'))){
				include_once($f);
				return true;
			}
			return false;
		}
		static private function exec_before_after($test_file,$include_file){
			if(strpos($test_file,getcwd()) === 0){
				$inc = array();
				$dir = dirname($test_file);
				while(strlen($dir) >= strlen(getcwd())){
					if(is_file($f=($dir.'/'.$include_file))) array_unshift($inc,$f);
					$dir = dirname($dir);
				}
				if($include_file == '__after__.php'){
					krsort($inc);
				}
				foreach($inc as $i) include($i);
			}else if(is_file($f=(dirname($test_file).$include_file))){
				include($f);
			}
		}
		/**
		 * 実行する
		 * @param string $test_path
		 * @throws \RuntimeException
		 * @return Ambigous <number>
		 */
		public static function exec($test_file){
			self::exec_before_after($test_file,'__before__.php');
			self::$start_time = microtime(true);
			self::$current_test = $test_file;
			
			try{
				ob_start();
					include($test_file);
				$rtn = ob_get_clean();
				
				if(preg_match('/(Parse|Fatal) error:.+/',$rtn,$m)){
					$err = (preg_match('/syntax error.+code on line\s*(\d+)/',$rtn,$line) ?
							'Parse error: syntax error '.$test_file.' code on line '.$line[1]
							: $m[0]);
					throw new \RuntimeException($err);
				}
				$res = array(1,0);
			}catch(\testman\AssertFailure $e){
				list($debug) = $e->getTrace();
				$res = array(-1,0,$debug['file'],$debug['line'],$e->getMessage(),$e->expectation(),$e->result(),$e->has());
				ob_end_clean();
			}catch(\Exception $e){
				$trace = $e->getTrace();
				for($i=sizeof($trace);$i>=0;$i--){
					if(isset($trace[$i]['file']) && $trace[$i]['file'] != __FILE__){
						if(!(substr(__FILE__,0,7) == 'phar://' && dirname(substr(__FILE__,7)) == $trace[$i]['file'])){
							$res = array(-2,0,$trace[$i]['file'],$trace[$i]['line'],(string)$e);
							break;
						}
					}
				}
				if(!isset($res)){
					$res = array(-2,0,$e->getFile(),$e->getLine(),(string)$e);
				}
				ob_end_clean();
			}
			$res[1] = (round(microtime(true) - self::$start_time,3));
			self::exec_before_after($test_file,'__after__.php');
			self::$resultset[$test_file] = $res;
			return $res[0];
		}
		/**
		 * 結果
		 * @return string[]
		 */
		public static function resultset(){
			return self::$resultset;
		}
		/**
		 * 実行対象の一覧
		 * @param string $testdir
		 * @throws \InvalidArgumentException
		 * @return string[]
		 */
		public static function get_list($testdir){
			$test_list = array();
			
			if(is_dir($testdir)){
				foreach(new \RegexIterator(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($testdir,
						\FilesystemIterator::CURRENT_AS_FILEINFO|\FilesystemIterator::SKIP_DOTS|\FilesystemIterator::UNIX_PATHS
				),\RecursiveIteratorIterator::SELF_FIRST),'/\/[^\.\_].*\.php$/') as $f){
					if(strpos($f->getPathname(),basename(__FILE__,'.php').'.') === false){
						$test_list[$f->getPathname()] = true;
					}
				}
			}else if(is_file($testdir)){
				$test_list[realpath($testdir)] = true;
			}else{
				throw new \InvalidArgumentException($testdir.' not');
			}
			ksort($test_list);
			
			return array_keys($test_list);
		}
		/**
		 * 結果のXMLを出力する
		 * @param string $output
		 * @return SimpleXMLElement|string
		 */
		public static function output($output){
			$xml = new \SimpleXMLElement('<testsuites></testsuites>');
			$get_testsuite = function($dir,&$testsuite) use($xml){
				if(empty($testsuite)){
					$testsuite = $xml->addChild('testsuite');
					$testsuite->addAttribute('name',$dir);
				}
				return $testsuite;
			};
		
			$list = array();
			foreach(self::resultset() as $file => $info){
				$list[dirname($file)][basename($file)] = $info;
			}
			$errors = $failures = $times = 0;
			foreach($list as $dir => $files){
				$testsuite = null;
				$dir_time = $dir_failures = $dir_errors = 0;
				
				foreach($files as $file => $info){
					switch($info[0]){
						case 1:
							list(,$time) = $info;
							$x = $get_testsuite($dir,$testsuite)->addChild('testcase');
							$x->addAttribute('name',basename($file));
							$x->addAttribute('time',$time);
		
							$dir_time += $time;
							break;
						case -1:
							list(,$time,$file,$line,$msg,$r1,$r2,$has) = $info;
							$dir_failures++;
		
							$x = $get_testsuite($dir,$testsuite)->addChild('testcase');
							$x->addAttribute('name',basename($file));
							$x->addAttribute('time',$time);
							$x->addAttribute('line',$line);
								
							if($has){
								ob_start();
								var_dump($r2);
								$failure_value = 'Line. '.$line.': '."\n".ob_get_clean();
								$failure = dom_import_simplexml($x->addChild('failure'));
								$failure->appendChild($failure->ownerDocument->createCDATASection($failure_value));
							}
							$dir_time += $time;
							break;
						case -2:
							list(,$time,$file,$line,$msg) = $info;
							$dir_errors++;
		
							$x = $get_testsuite($dir,$testsuite)->addChild('testcase');
							$x->addAttribute('name',basename($file));
							$x->addAttribute('time',$time);
							$x->addAttribute('line',$line);
								
							$error_value = 'Line. '.$line.': '.$msg;
							$error = $x->addChild('error');
							$error->addAttribute('line',$line);
							$error_node = dom_import_simplexml($error);
							$error_node->appendChild($error_node->ownerDocument->createCDATASection($error_value));
		
							$dir_time += $time;
							break;
					}
				}
				if(!empty($testsuite)){
					$testsuite->addAttribute('tests',sizeof($files));
					$testsuite->addAttribute('failures',$dir_failures);
					$testsuite->addAttribute('errors',$dir_errors);
					$testsuite->addAttribute('time',$dir_time);
				}
				$failures += $dir_failures;
				$errors += $dir_errors;
				$times += $dir_time;
			}
			$xml->addAttribute('tests',sizeof(self::resultset()));
			$xml->addAttribute('failures',$failures);
			$xml->addAttribute('errors',$errors);
			$xml->addAttribute('time',$times);
			$xml->addAttribute('create_date',date('Y/m/d H:i:s'));
			$xml->addChild('system-out');
			$xml->addChild('system-err');
				
			file_put_contents($output,$xml->asXML());
			
			return realpath($output);
		}
		/**
		 * 対象のテスト群を実行する
		 * @param string $testdir
		 */
		public static function start($testdir){
			$tab = '  ';
			$success = $fail = $exception = $exe_time = $use_memory = 0;			
			
			\testman\Std::println_warning('Progress:');
			$test_list = self::get_list($testdir);
			
			print(' ');
			print(str_repeat('+',sizeof($test_list)));
			print("\033[".(sizeof($test_list)+2)."D");
			print(\testman\Runner::fixture() ? "\033[32m@\033[0m" : '@');
			print(' ');			
			
			$start_time = microtime(true);
			$start_mem = round(number_format((memory_get_usage() / 1024 / 1024),3),4);
			$exe_time = round((microtime(true) - (float)$start_time),4);
			$use_memory = round(number_format((memory_get_usage() / 1024 / 1024),3),4);
			
			// excute
			foreach($test_list as $test_path){
				print("/\033[1D");
				$status = \testman\Runner::exec($test_path);
				print("\033[".(($status == 1) ? 32 : 31)."m*\033[0m");
			}
			print(PHP_EOL);
			
			\testman\Std::println();
			\testman\Std::println_warning('Results:');
			
			foreach(\testman\Runner::resultset() as $file => $info){
				switch($info[0]){
					case 1:
						$success++;
						break;
					case -1:
						$fail++;
						list(,$time,$file,$line,$msg,$r1,$r2,$has) = $info;
			
						\testman\Std::println();
						\testman\Std::println_primary($file);
						\testman\Std::println_danger('['.$line.']: '.$msg);
			
						if($has){
							\testman\Std::println($tab.str_repeat('-',70));
							ob_start();
							var_dump($r1);
							$diff1 = ob_get_clean();
							\testman\Std::println($tab.str_replace(PHP_EOL,PHP_EOL.$tab,$diff1));
								
							\testman\Std::println($tab.str_repeat('-',70));
							ob_start();
							var_dump($r2);
							$diff2 = ob_get_clean();
							\testman\Std::println($tab.str_replace(PHP_EOL,PHP_EOL.$tab,$diff2));
						}
						break;
					case -2:
						$exception++;
						list(,$time,$file,$line,$msg) = $info;
			
						\testman\Std::println();
						\testman\Std::println_primary($file);
						\testman\Std::println_danger('['.$line.']: '.$msg);
						break;
				}
			}
			\testman\Std::println(str_repeat('=',80));
			\testman\Std::println_info(sprintf('success %d, failures %d, errors %d (%.05f sec / %s MByte)',$success,$fail,$exception,$exe_time,$use_memory));
			\testman\Std::println();			
		}
	}
	class Coverage{
		static private $db;
		static private $result = array();
		static private $linkvars = array();
		
		/**
		 * リンクファイルが存在するか
		 * @param mixed $vars
		 * @return boolean
		 */
		public static function has_link(&$vars){
			if(empty(self::$linkvars)){
				return false;
			}
			$vars = self::$linkvars;
			return true;
		}
		/**
		 * リンクファイル名
		 * @return string
		 */
		public static function link(){
			return '_test_link'.md5(__FILE__);
		}
		/**
		 * 測定を開始する
		 * @param string $output_xml 書き出し先
		 * @throws \RuntimeException
		 */
		public static function start($output_xml,$target_dir=null){
			if(!empty($output_xml)){
				if(extension_loaded('xdebug')){
					self::$db = $output_xml;
					$target_list_db = self::$db.'.target';
					$tmp_db = self::$db.'.tmp';
					
					if(!is_dir($d = dirname(self::$db))){
						mkdir($d,0777,true);
					}
					file_put_contents(self::$db,'');
					file_put_contents($target_list_db,'');
					file_put_contents($tmp_db,'');
					
					if(empty($target_dir)){
						$target_dir = getcwd().'/lib';
					}
					if(!empty($target_dir) && is_dir($target_dir)){
						$fp = fopen($target_list_db,'w');
						
						foreach(new \RecursiveIteratorIterator(
							new \RecursiveDirectoryIterator(
								$target_dir,
								\FilesystemIterator::SKIP_DOTS|\FilesystemIterator::UNIX_PATHS
							),\RecursiveIteratorIterator::SELF_FIRST
						) as $f){
							if($f->isFile() &&
								substr($f->getFilename(),-4) == '.php' &&
								ctype_upper(substr($f->getFilename(),0,1))
							){
								fwrite($fp,$f->getPathname().PHP_EOL);
							}
						}
						fclose($fp);
					}
					xdebug_start_code_coverage(XDEBUG_CC_UNUSED|XDEBUG_CC_DEAD_CODE);
					self::$linkvars['coverage_data_file'] = self::$db;
					
					return true;
				}else{
					throw new \RuntimeException('xdebug extension not loaded');
				}
			}
			return false;
		}
		/**
		 * 測定を終了する
		 */
		public static function stop(){
			if(is_file(self::$db)){
				$target_list_db = self::$db.'.target';
				$tmp_db = self::$db.'.tmp';
				
				$target_list = explode(PHP_EOL,trim(file_get_contents($target_list_db)));
				$fp = fopen($tmp_db,'a');

				foreach(xdebug_get_code_coverage() as $file_path => $lines){
					if(false !== ($i = array_search($file_path,$target_list))){
						fwrite($fp,json_encode(array($i,$lines)).PHP_EOL);
					}
				}
				fclose($fp);
					
				foreach(explode(PHP_EOL,trim(file_get_contents($tmp_db))) as $json){
					if(!empty($json)){
						$cov = json_decode($json,true);
						if($cov !== false){
							$filename = $target_list[$cov[0]];
			
							if(!isset(self::$result[$filename])){
								self::$result[$filename] = array('covered_line_status'=>array(),'uncovered_line_status'=>array(),'exec'=>1);
							}
							foreach($cov[1] as $line => $status){
								if($status == 1){
									self::$result[$filename]['covered_line_status'][] = $line;
								}else if($status != -2){
									self::$result[$filename]['uncovered_line_status'][] = $line;
								}
							}
						}
					}
				}
				foreach(self::$result as $filename => $cov){
					self::$result[$filename]['covered_line'] = array_unique(self::$result[$filename]['covered_line_status']);
					self::$result[$filename]['uncovered_line'] = array_diff(array_unique(self::$result[$filename]['uncovered_line_status']),self::$result[$filename]['covered_line']);
					unset(self::$result[$filename]['covered_line_status']);
					unset(self::$result[$filename]['uncovered_line_status']);
				}
				foreach($target_list as $filename){
					if(!isset(self::$result[$filename])){
						self::$result[$filename] = array('covered_line'=>array(),'uncovered_line'=>array(),'exec'=>0);
					}
				}
				ksort(self::$result);
				unlink($target_list_db);
				unlink($tmp_db);

				xdebug_stop_code_coverage();
				return true;
			}
			return false;
		}
		/**
		 * 結果の取得
		 * @return string[]
		 */
		public static function get(){
			return self::$result;
		}
		/**
		 * startで指定した$work_dirに結果のXMLを出力する
		 * @param string $display 
		 */
		public static function output($display=true){
			if($display){
				\testman\Std::println();
				\testman\Std::println_warning('Coverage: ');
			}
			$xml = new \SimpleXMLElement('<coverage></coverage>');

			$total_covered = $total_lines = 0;
			foreach(self::get() as $filename => $resultset){
				$covered = count($resultset['covered_line']);
				$uncovered = count($resultset['uncovered_line']);
				$total_covered += $covered;
				$total_lines += $covered + $uncovered;
				$covered = (($resultset['exec'] == 1) ? ceil($covered / ($covered + $uncovered) * 100) : 0);
					
				$f = $xml->addChild('file');
				$f->addAttribute('name',$filename);
				$f->addAttribute('covered',$covered);
				$f->addAttribute('modify_date',date('Y/m/d H:i:s',filemtime($filename)));
				$f->addChild('covered_lines',implode(',',$resultset['covered_line']));
				$f->addChild('uncovered_lines',implode(',',$resultset['uncovered_line']));
				
				if($display){
					$msg = sprintf(' %3d%% %s',$covered,$filename);				
					if($covered == 100){
						\testman\Std::println_success($msg);
					}else if($covered > 50){
						\testman\Std::println_warning($msg);
					}else{
						\testman\Std::println_danger($msg);						
					}
				}
			}
			$covered_sum = ($total_covered == 0) ? 0 : ceil($total_covered/$total_lines*100);
			
			$xml->addAttribute('create_date',date('Y/m/d H:i:s'));
			$xml->addAttribute('covered',$covered_sum);
			$xml->addAttribute('lines',$total_lines);
			$xml->addAttribute('covered_lines',$total_covered);
			file_put_contents(self::$db,$xml->asXML());
			
			if($display){
				\testman\Std::println(str_repeat('-',70));
				\testman\Std::println_info(sprintf(' Covered %s%%',$covered_sum));
				\testman\Std::println_primary(PHP_EOL.'Written Coverage: '.realpath(self::$db));
			}
		}
	}
	/**
	 * XMLを処理する
	 */
	class Xml implements \IteratorAggregate{
		private $attr = array();
		private $plain_attr = array();
		private $name;
		private $value;
		private $close_empty = true;
	
		private $plain;
		private $pos;
		private $esc = true;
	
		public function __construct($name=null,$value=null){
			if($value === null && is_object($name)){
				$n = explode('\\',get_class($name));
				$this->name = array_pop($n);
				$this->value($name);
			}else{
				$this->name = trim($name);
				$this->value($value);
			}
		}
		/**
		 * (non-PHPdoc)
		 * @see IteratorAggregate::getIterator()
		 */
		public function getIterator(){
			return new \ArrayIterator($this->attr);
		}
		/**
		 * 値が無い場合は閉じを省略する
		 * @param boolean
		 * @return boolean
		 */
		public function close_empty(){
			if(func_num_args() > 0) $this->close_empty = (boolean)func_get_arg(0);
			return $this->close_empty;
		}
		/**
		 * エスケープするか
		 * @param boolean $bool
		 */
		public function escape($bool){
			$this->esc = (boolean)$bool;
			return $this;
		}
		/**
		 * setできた文字列
		 * @return string
		 */
		public function plain(){
			return $this->plain;
		}
		/**
		 * 子要素検索時のカーソル
		 * @return integer
		 */
		public function cur(){
			return $this->pos;
		}
		/**
		 * 要素名
		 * @return string
		 */
		public function name($name=null){
			if(isset($name)) $this->name = $name;
			return $this->name;
		}
		private function get_value($v){
			if($v instanceof self){
				$v = $v->get();
			}else if(is_bool($v)){
				$v = ($v) ? 'true' : 'false';
			}else if($v === ''){
				$v = null;
			}else if(is_array($v) || is_object($v)){
				$r = '';
				foreach($v as $k => $c){
					if(is_numeric($k) && is_object($c)){
						$e = explode('\\',get_class($c));
						$k = array_pop($e);
					}
					if(is_numeric($k)) $k = 'data';
					$x = new self($k,$c);
					$x->escape($this->esc);
					$r .= $x->get();
				}
				$v = $r;
			}else if($this->esc && strpos($v,'<![CDATA[') === false && preg_match("/&|<|>|\&[^#\da-zA-Z]/",$v)){
				$v = '<![CDATA['.$v.']]>';
			}
			return $v;
		}
		/**
		 * 値を設定、取得する
		 * @param mixed
		 * @param boolean
		 * @return string
		 */
		public function value(){
			if(func_num_args() > 0) $this->value = $this->get_value(func_get_arg(0));
			if(strpos($this->value,'<![CDATA[') === 0) return substr($this->value,9,-3);
			return $this->value;
		}
		/**
		 * 値を追加する
		 * ２つ目のパラメータがあるとアトリビュートの追加となる
		 * @param mixed $arg
		 */
		public function add($arg){
			if(func_num_args() == 2){
				$this->attr(func_get_arg(0),func_get_arg(1));
			}else{
				$this->value .= $this->get_value($arg);
			}
			return $this;
		}
		/**
		 * アトリビュートを取得する
		 * @param string $n 取得するアトリビュート名
		 * @param string $d アトリビュートが存在しない場合の代替値
		 * @return string
		 */
		public function in_attr($n,$d=null){
			return isset($this->attr[strtolower($n)]) ? ($this->esc ? htmlentities($this->attr[strtolower($n)],ENT_QUOTES,'UTF-8') : $this->attr[strtolower($n)]) : (isset($d) ? (string)$d : null);
		}
		/**
		 * アトリビュートから削除する
		 * パラメータが一つも無ければ全件削除
		 */
		public function rm_attr(){
			if(func_num_args() === 0){
				$this->attr = array();
			}else{
				foreach(func_get_args() as $n) unset($this->attr[$n]);
			}
		}
		/**
		 * アトリビュートがあるか
		 * @param string $name
		 * @return boolean
		 */
		public function is_attr($name){
			return array_key_exists($name,$this->attr);
		}
		/**
		 * アトリビュートを設定
		 * @return self $this
		 */
		public function attr($key,$value){
			$this->attr[strtolower($key)] = is_bool($value) ? (($value) ? 'true' : 'false') : $value;
			return $this;
		}
		/**
		 * 値の無いアトリビュートを設定
		 * @param string $v
		 */
		public function plain_attr($v){
			$this->plain_attr[] = $v;
		}
		/**
		 * XML文字列を返す
		 */
		public function get($encoding=null){
			if($this->name === null) throw new \LogicException('undef name');
			$attr = '';
			$value = ($this->value === null || $this->value === '') ? null : (string)$this->value;
			
			foreach(array_keys($this->attr) as $k){
				$attr .= ' '.$k.'="'.$this->in_attr($k).'"';
			}
			return ((empty($encoding)) ? '' : '<?xml version="1.0" encoding="'.$encoding.'" ?'.'>'.PHP_EOL)
					.('<'.$this->name.$attr.(implode(' ',$this->plain_attr)).(($this->close_empty && !isset($value)) ? ' /' : '').'>')
					.$this->value
					.((!$this->close_empty || isset($value)) ? sprintf('</%s>',$this->name) : '');
		}
		public function __toString(){
			return $this->get();
		}
		/**
		 * 検索する
		 * @param string $name
		 * @param integer $offset
		 * @param integer $length
		 * @return \testman\XmlIterator
		 */
		public function find($name,$offset=0,$length=0){
			if(is_string($name) && strpos($name,'/') !== false){
				list($name,$path) = explode('/',$name,2);
				foreach(new \testman\XmlIterator($name,$this->value(),0,0) as $t){
					try{
						$it = $t->find($path,$offset,$length);
						if($it->valid()){
							reset($it);
							return $it;
						}
					}catch(\testman\NotFoundException $e){}
				}
				throw new \testman\NotFoundException();
			}
			return new \testman\XmlIterator($name,$this->value(),$offset,$length);
		}
		/**
		 * １件取得する
		 * @param string $name
		 * @param integer $offset
		 * @throws \testman\NotFoundException
		 * @return \testman\Xml
		 */
		public function find_get($name,$offset=0){
			foreach($this->find($name,$offset,1) as $x){
				return $x;
			}
			throw new \testman\NotFoundException($name.' not found');
		}
		/**
		 * 匿名タグとしてインスタンス生成
		 * @param string $value
		 * @return \testman\Xml
		 */
		public static function anonymous($value){
			$xml = new self('XML'.uniqid());
			$xml->escape(false);
			$xml->value($value);
			$xml->escape(true);
			return $xml;
		}
		/**
		 * タグの検出
		 * @param string $plain
		 * @param string $name
		 * @throws \testman\NotFoundException
		 * @return \testman\Xml
		 */
		public static function extract($plain,$name=null){
			if(!(!empty($name) && strpos($plain,$name) === false) && self::find_extract($x,$plain,$name)){
				return $x;
			}
			throw new \testman\NotFoundException($name.' not found');
		}
		static private function find_extract(&$x,$plain,$name=null,$vtag=null){
			$plain = (string)$plain;
			$name = (string)$name;
			if(empty($name) && preg_match("/<([\w\:\-]+)[\s][^>]*?>|<([\w\:\-]+)>/is",$plain,$m)){
				$name = str_replace(array("\r\n","\r","\n"),'',(empty($m[1]) ? $m[2] : $m[1]));
			}
			$qname = preg_quote($name,'/');
			if(!preg_match("/<(".$qname.")([\s][^>]*?)>|<(".$qname.")>/is",$plain,$parse,PREG_OFFSET_CAPTURE)) return false;
			$x = new self();
			$x->pos = $parse[0][1];
			$balance = 0;
			$attrs = '';
	
			if(substr($parse[0][0],-2) == '/>'){
				$x->name = $parse[1][0];
				$x->plain = empty($vtag) ? $parse[0][0] : preg_replace('/'.preg_quote(substr($vtag,0,-1).' />','/').'/',$vtag,$parse[0][0],1);
				$attrs = $parse[2][0];
			}else if(preg_match_all("/<[\/]{0,1}".$qname."[\s][^>]*[^\/]>|<[\/]{0,1}".$qname."[\s]*>/is",$plain,$list,PREG_OFFSET_CAPTURE,$x->pos)){
				foreach($list[0] as $arg){
					if(($balance += (($arg[0][1] == '/') ? -1 : 1)) <= 0 &&
							preg_match("/^(<(".$qname.")([\s]*[^>]*)>)(.*)(<\/\\2[\s]*>)$/is",
									substr($plain,$x->pos,($arg[1] + strlen($arg[0]) - $x->pos)),
									$match
							)
					){
						$x->plain = $match[0];
						$x->name = $match[2];
						$x->value = ($match[4] === '' || $match[4] === null) ? null : $match[4];
						$attrs = $match[3];
						break;
					}
				}
				if(!isset($x->plain)){
					return self::find_extract($x,preg_replace('/'.preg_quote($list[0][0][0],'/').'/',substr($list[0][0][0],0,-1).' />',$plain,1),$name,$list[0][0][0]);
				}
			}
			if(!isset($x->plain)) return false;
			if(!empty($attrs)){
				if(preg_match_all("/[\s]+([\w\-\:]+)[\s]*=[\s]*([\"\'])([^\\2]*?)\\2/ms",$attrs,$attr)){
					foreach($attr[0] as $id => $value){
						$x->attr($attr[1][$id],$attr[3][$id]);
						$attrs = str_replace($value,'',$attrs);
					}
				}
				if(preg_match_all("/([\w\-]+)/",$attrs,$attr)){
					foreach($attr[1] as $v) $x->attr($v,$v);
				}
			}
			return true;
		}
	}
	class XmlIterator implements \Iterator{
		private $name = null;
		private $plain = null;
		private $tag = null;
		private $offset = 0;
		private $length = 0;
		private $count = 0;
	
		public function __construct($tag_name,$value,$offset,$length){
			$this->name = $tag_name;
			$this->plain = $value;
			$this->offset = $offset;
			$this->length = $length;
			$this->count = 0;
		}
		public function key(){
			$this->tag->name();
		}
		public function current(){
			$this->plain = substr($this->plain,0,$this->tag->cur()).substr($this->plain,$this->tag->cur() + strlen($this->tag->plain()));
			$this->count++;
			return $this->tag;
		}
		public function valid(){
			if($this->length > 0 && ($this->offset + $this->length) <= $this->count){
				return false;
			}
			if(is_string($this->name) && strpos($this->name,'|') !== false){
				$this->name = explode('|',$this->name);
			}
			if(is_array($this->name)){
				$tags = array();
				foreach($this->name as $name){
					try{
						$get_tag = \testman\Xml::extract($this->plain,$name);
						$tags[$get_tag->cur()] = $get_tag;
					}catch(\testman\NotFoundException $e){
					}
				}
				if(empty($tags)) return false;
				ksort($tags,SORT_NUMERIC);
				foreach($tags as $this->tag) return true;
			}
			try{
				$this->tag = \testman\Xml::extract($this->plain,$this->name);
				return true;
			}catch(\testman\NotFoundException $e){
			}
			return false;
		}
		public function next(){
		}
		public function rewind(){
			for($i=0;$i<$this->offset;$i++){
				if($this->valid()){
					$this->current();
				}
			}
		}
	}
	class Browser{
		private $resource;
		private $agent;
		private $timeout = 30;
		private $redirect_max = 20;
		private $redirect_count = 1;
	
		private $request_header = array();
		private $request_vars = array();
		private $request_file_vars = array();
		private $head;
		private $body;
		private $cookie = array();
		private $url;
		private $status;
	
		public function __construct($agent=null,$timeout=30,$redirect_max=20){
			$this->agent = $agent;
			$this->timeout = (int)$timeout;
			$this->redirect_max = (int)$redirect_max;
		}
		/**
		 * 最大リダイレクト回数
		 * @param integer $redirect_max
		 */
		public function redirect_max($redirect_max){
			$this->redirect_max = (integer)$redirect_max;
		}
		/**
		 * タイムアウトするまでの秒数
		 * @param integer $timeout
		 */
		public function timeout($timeout){
			$this->timeout = (int)$timeout;
		}
		/**
		 * リクエスト時のユーザエージェント
		 * @param string $agent
		 */
		public function agent($agent){
			$this->agent = $agent;
		}
		public function __toString(){
			return $this->body();
		}
		/**
		 * リクエスト時のヘッダ
		 * @param string $key
		 * @param string $value
		 */
		public function header($key,$value=null){
			$this->request_header[$key] = $value;
		}
		/**
		 * リクエスト時のクエリ
		 * @param string $key
		 * @param string $value
		 */
		public function vars($key,$value=null){
			if(is_bool($value)) $value = ($value) ? 'true' : 'false';
			$this->request_vars[$key] = $value;
			if(isset($this->request_file_vars[$key])) unset($this->request_file_vars[$key]);
		}
		/**
		 * リクエスト時の添付ファイル
		 * @param string $key
		 * @param string $filepath
		 */
		public function file_vars($key,$filepath){
			$this->request_file_vars[$key] = $filepath;
			if(isset($this->request_vars[$key])) unset($this->request_vars[$key]);
		}
		/**
		 * リクエストクエリがセットされているか
		 * @param string $key
		 * @return boolean
		 */
		public function has_vars($key){
			return (array_key_exists($key,$this->request_vars) || array_key_exists($key,$this->request_file_vars));
		}
		/**
		 * curlへのオプション
		 * @param string $key
		 * @param string $value
		 */
		public function setopt($key,$value){
			if(!isset($this->resource)) $this->resource = curl_init();
			curl_setopt($this->resource,$key,$value);
		}
		/**
		 * 結果ヘッダの取得
		 * @returnstring
		 */
		public function head(){
			return $this->head;
		}
		/**
		 * 結果ボディーの取得
		 * @return string
		 */
		public function body(){
			return ($this->body === null || is_bool($this->body)) ? '' : $this->body;
		}
		/**
		 * 最終実行URL
		 * @return string
		 */
		public function url(){
			return $this->url;
		}
		/**
		 * 最終HTTPステータス
		 * @return integer
		 */
		public function status(){
			return empty($this->status) ? null : (int)$this->status;
		}
		/**
		 * HEADでリクエスト
		 * @param string $url
		 * @return self
		 */
		public function do_head($url){
			return $this->request('HEAD',$url);
		}
		/**
		 * PUTでリクエスト
		 * @param string $url
		 * @return self
		 */
		public function do_put($url){
			return $this->request('PUT',$url);
		}
		/**
		 * DELETEでリクエスト
		 * @param string $url
		 * @return self
		 */
		public function do_delete($url){
			return $this->request('DELETE',$url);
		}
		/**
		 * GETでリクエスト
		 * @param string $url
		 * @return self
		 */
		public function do_get($url){
			return $this->request('GET',$url);
		}
		/**
		 * POSTでリクエスト
		 * @param string $url
		 * @return self
		 */
		public function do_post($url){
			return $this->request('POST',$url);
		}
		/**
		 * GETでリクエストしてダウンロード
		 * @param string $url
		 * @param string $download_path 保存パス
		 * @return self
		 */
		public function do_download($url,$download_path){
			return $this->request('GET',$url,$download_path);
		}
		/**
		 * POSTでリクエストしてダウンロード
		 * @param string $url
		 * @param string $download_path 保存パス
		 * @return self
		 */
		public function do_post_download($url,$download_path){
			return $this->request('POST',$url,$download_path);
		}
		public function callback_head($resource,$data){
			$this->head .= $data;
			return strlen($data);
		}
		public function callback_body($resource,$data){
			$this->body .= $data;
			return strlen($data);
		}
		private function request($method,$url,$download_path=null){
			if(!isset($this->resource)) $this->resource = curl_init();
			$url_info = parse_url($url);
			$host = isset($url_info['host']) ? $url_info['host'] : '';
			$cookie_base_path = $host.(isset($url_info['path']) ? $url_info['path'] : '');
			if(\testman\Coverage::has_link($vars)){
				$this->request_vars[\testman\Coverage::link()] = $vars;
			}
			if(isset($url_info['query'])){
				parse_str($url_info['query'],$vars);
				foreach($vars as $k => $v){
					if(!isset($this->request_vars[$k])){
						$this->request_vars[$k] = $v;
					}
				}
				list($url) = explode('?',$url,2);
			}
			switch($method){
				case 'POST': curl_setopt($this->resource,CURLOPT_POST,true); break;
				case 'GET': curl_setopt($this->resource,CURLOPT_HTTPGET,true); break;
				case 'HEAD': curl_setopt($this->resource,CURLOPT_NOBODY,true); break;
				case 'PUT': curl_setopt($this->resource,CURLOPT_PUT,true); break;
				case 'DELETE': curl_setopt($this->resource,CURLOPT_CUSTOMREQUEST,'DELETE'); break;
			}
			switch($method){
				case 'POST':
					if(!empty($this->request_file_vars)){
						$vars = array();
						if(!empty($this->request_vars)){
							foreach(explode('&',http_build_query($this->request_vars)) as $q){
								$s = explode('=',$q,2);
								$vars[urldecode($s[0])] = isset($s[1]) ? urldecode($s[1]) : null;
							}
						}
						foreach(explode('&',http_build_query($this->request_file_vars)) as $q){
							$s = explode('=',$q,2);
							if(isset($s[1])){
								if(!is_file($f=urldecode($s[1]))) throw new \RuntimeException($f.' not found');
								$vars[urldecode($s[0])] = (class_exists('\\CURLFile',false)) ? new \CURLFile($f) : '@'.$f;
							}
						}
						curl_setopt($this->resource,CURLOPT_POSTFIELDS,$vars);
					}else{
						curl_setopt($this->resource,CURLOPT_POSTFIELDS,http_build_query($this->request_vars));
					}
					break;
				case 'GET':
				case 'HEAD':
				case 'PUT':
				case 'DELETE':
					$url = $url.(!empty($this->request_vars) ? '?'.http_build_query($this->request_vars) : '');
			}
			curl_setopt($this->resource,CURLOPT_URL,$url);
			curl_setopt($this->resource,CURLOPT_FOLLOWLOCATION,false);
			curl_setopt($this->resource,CURLOPT_HEADER,false);
			curl_setopt($this->resource,CURLOPT_RETURNTRANSFER,false);
			curl_setopt($this->resource,CURLOPT_FORBID_REUSE,true);
			curl_setopt($this->resource,CURLOPT_FAILONERROR,false);
			curl_setopt($this->resource,CURLOPT_TIMEOUT,$this->timeout);
	
			if(\testman\Conf::get('ssl-verify',true) === false){
				curl_setopt($this->resource, CURLOPT_SSL_VERIFYHOST,false);
				curl_setopt($this->resource, CURLOPT_SSL_VERIFYPEER,false);
			}			
			if(!isset($this->request_header['Expect'])){
				$this->request_header['Expect'] = null;
			}
			if(!isset($this->request_header['Cookie'])){
				$cookies = '';
				foreach($this->cookie as $domain => $cookie_value){
					if(strpos($cookie_base_path,$domain) === 0 || strpos($cookie_base_path,(($domain[0] == '.') ? $domain : '.'.$domain)) !== false){
						foreach($cookie_value as $k => $v){
							if(!$v['secure'] || ($v['secure'] && substr($url,0,8) == 'https://')) $cookies .= sprintf('%s=%s; ',$k,$v['value']);
						}
					}
				}
				curl_setopt($this->resource,CURLOPT_COOKIE,$cookies);
			}
			if(!isset($this->request_header['User-Agent'])){
				curl_setopt($this->resource,CURLOPT_USERAGENT,
				(empty($this->agent) ?
				(isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null) :
				$this->agent
				)
				);
			}
			if(!isset($this->request_header['Accept']) && isset($_SERVER['HTTP_ACCEPT'])){
				$this->request_header['Accept'] = $_SERVER['HTTP_ACCEPT'];
			}
			if(!isset($this->request_header['Accept-Language']) && isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])){
				$this->request_header['Accept-Language'] = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
			}
			if(!isset($this->request_header['Accept-Charset']) && isset($_SERVER['HTTP_ACCEPT_CHARSET'])){
				$this->request_header['Accept-Charset'] = $_SERVER['HTTP_ACCEPT_CHARSET'];
			}
			curl_setopt($this->resource,
				CURLOPT_HTTPHEADER,
				array_map(
					function($k,$v){
						return $k.': '.$v;
					},
					array_keys($this->request_header),
					$this->request_header
				)
			);
			curl_setopt($this->resource,CURLOPT_HEADERFUNCTION,array($this,'callback_head'));
	
			if(empty($download_path)){
				curl_setopt($this->resource,CURLOPT_WRITEFUNCTION,array($this,'callback_body'));
			}else{
				if(!is_dir(dirname($download_path))) mkdir(dirname($download_path),0777,true);
				$fp = fopen($download_path,'wb');
				
				curl_setopt($this->resource,CURLOPT_WRITEFUNCTION,function($resource,$data) use(&$fp){
					if($fp) fwrite($fp,$data);
					return strlen($data);
				});
			}
			$this->request_header = $this->request_vars = array();
			$this->head = $this->body = '';
			curl_exec($this->resource);
			if(!empty($download_path) && $fp){
				fclose($fp);
			}
			if(($err_code = curl_errno($this->resource)) > 0){
				if($err_code == 47) return $this;
				throw new \RuntimeException($err_code.': '.curl_error($this->resource).', ['.$method.'] '.$url);
			}
	
			$this->status = curl_getinfo($this->resource,CURLINFO_HTTP_CODE);
			$this->url = curl_getinfo($this->resource,CURLINFO_EFFECTIVE_URL);
	
			if(strpos($this->url,'?') !== false){
				list($url,$query) = explode('?',$this->url,2);
				if(!empty($query)){
					parse_str($query,$vars);
					if(isset($vars[\testman\Coverage::link()])){
						unset($vars[\testman\Coverage::link()]);
					}
					if(!empty($vars)){
						$url = $url.'?'.http_build_query($vars);
					}
				}
				$this->url = $url;
			}
			if(preg_match_all('/Set-Cookie:[\s]*(.+)/i',$this->head,$match)){
				foreach($match[1] as $cookies){
					$cookie_name = $cookie_value = $cookie_domain = $cookie_path = $cookie_expires = null;
					$cookie_domain = $host;
					$cookie_path = '/';
					$secure = false;
					
					foreach(explode(';',$cookies) as $cookie){
						$cookie = trim($cookie);
						if(strpos($cookie,'=') !== false){
							list($k,$v) = explode('=',$cookie,2);
							$k = trim($k);
							$v = trim($v);
							switch(strtolower($k)){
								case 'expires': $cookie_expires = ctype_digit($v) ? (int)$v : strtotime($v); break;
								case 'domain': $cookie_domain = preg_replace('/^[\w]+:\/\/(.+)$/','\\1',$v); break;
								case 'path': $cookie_path = $v; break;
								default:
									if(!isset($cookie_name)){
										$cookie_name = $k;
										$cookie_value = $v;
									}
							}
						}else if(strtolower($cookie) == 'secure'){
							$secure = true;
						}
					}
					$cookie_domain = $cookie_domain.$cookie_path;
					
					if($cookie_expires !== null && $cookie_expires < time()){
						if(isset($this->cookie[$cookie_domain][$cookie_name])) unset($this->cookie[$cookie_domain][$cookie_name]);
					}else{
						$this->cookie[$cookie_domain][$cookie_name] = array('value'=>$cookie_value,'expires'=>$cookie_expires,'secure'=>$secure);
					}
				}
			}
			curl_close($this->resource);
			unset($this->resource);
	
			if($this->redirect_count++ < $this->redirect_max){
				switch($this->status){
					case 300:
					case 301:
					case 302:
					case 303:
					case 307:
						if(preg_match('/Location:[\040](.*)/i',$this->head,$redirect_url)){
							return $this->request('GET',trim($redirect_url[1]),$download_path);
						}
				}
			}
			$this->redirect_count = 1;
	
			return $this;
		}
		public function __destruct(){
			if(isset($this->resource)) curl_close($this->resource);
		}
		/**
		 * bodyを解析しXMLオブジェクトとして返す
		 * @return \testman\Xml
		 */
		public function xml(){
			return \testman\Xml::extract($this->body());
		}
	}
	class Args{
		static private $opt = array();
		static private $value = array();
	
		/**
		 * 初期化
		 */
		public static function init(){
			$opt = $value = array();
			$argv = array_slice((isset($_SERVER['argv']) ? $_SERVER['argv'] : array()),1);
			
			for($i=0;$i<sizeof($argv);$i++){
				if(substr($argv[$i],0,2) == '--'){
					$opt[substr($argv[$i],2)][] = ((isset($argv[$i+1]) && $argv[$i+1][0] != '-') ? $argv[++$i] : true);
				}else if(substr($argv[$i],0,1) == '-'){
					$keys = str_split(substr($argv[$i],1),1);
					if(count($keys) == 1){
						$opt[$keys[0]][] = ((isset($argv[$i+1]) && $argv[$i+1][0] != '-') ? $argv[++$i] : true);
					}else{
						foreach($keys as $k){
							$opt[$k][] = true;
						}
					}
				}else{
					$value[] = $argv[$i];
				}
			}
			self::$opt = $opt;
			self::$value = $value;
		}
		/**
		 * オプション値の取得
		 * @param string $name
		 * @param string $default
		 * @return string
		 */
		public static function opt($name,$default=false){
			return array_key_exists($name,self::$opt) ? self::$opt[$name][0] : $default;
		}
		/**
		 * 引数の取得
		 * @param string $default
		 * @return string
		 */
		public static function value($default=null){
			return isset(self::$value[0]) ? self::$value[0] : $default;
		}
		/**
		 * オプション値を配列として取得
		 * @param unknown $name
		 * @return multitype:
		 */
		public static function opts($name){
			return array_key_exists($name,self::$opt) ? self::$opt[$name] : array();
		}
		/**
		 * 引数を全て取得
		 * @return multitype:
		 */
		public static function values(){
			return self::$value;
		}
	}
	class Std{
		/**
		 * 色付きでプリント
		 * @param string $msg
		 * @param string $color ANSI Colors
		 */
		public static function println($msg='',$color='0'){
			if(substr(PHP_OS,0,3) != 'WIN'){
				print("\033[".$color."m");
				print($msg.PHP_EOL);
				print("\033[0m");
			}else{
				print($msg.PHP_EOL);
			}
		}
		/**
		 * White
		 * @param string $msg
		 */
		public static function println_default($msg){
			self::println($msg,'37');
		}
		/**
		 * Blue
		 * @param string $msg
		 */
		public static function println_primary($msg){
			self::println($msg,'34');
		}
		/**
		 * Green
		 * @param string $msg
		 */
		public static function println_success($msg){
			self::println($msg,'32');
		}
		/**
		 * Cyan
		 * @param string $msg
		 */
		public static function println_info($msg){
			self::println($msg,'36');
		}
		/**
		 * Yellow
		 * @param string $msg
		 */
		public static function println_warning($msg){
			self::println($msg,'33');
		}
		/**
		 * Red
		 * @param string $msg
		 */
		public static function println_danger($msg){
			self::println($msg,'31');
		}
	}

}

namespace{
	// coverage client
	if(php_sapi_name() !== 'cli'){
		$linkkey = \testman\Coverage::link();
		
		if(isset($_POST[$linkkey]) || isset($_GET[$linkkey])){
			$linkvars = isset($_POST[$linkkey]) ? $_POST[$linkkey] : (isset($_GET[$linkkey]) ? $_GET[$linkkey] : array());
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
							$target = explode(PHP_EOL,file_get_contents($target_list_db));
							$fp = fopen($tmp_db,'a');
		
							foreach(xdebug_get_code_coverage() as $file_path => $lines){
								if(false !== ($i = array_search($file_path,$target))){
									fwrite($fp,json_encode(array($i,$lines)).PHP_EOL);
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
	if(!function_exists('failure')){
		function failure($msg='failure'){
			\testman\Assert::failure($msg);
		}
	}
	if(!function_exists('eq')){
		function eq($expectation,$result,$msg='failure equals'){
			\testman\Assert::eq($expectation,$result,$msg);
		}
	}
	if(!function_exists('neq')){
		function neq($expectation,$result,$msg='failure not equals'){
			\testman\Assert::neq($expectation,$result,$msg);
		}
	}
	if(!function_exists('meq')){
		function meq($keyword,$src,$msg='failure match'){
			\testman\Assert::meq($keyword,$src,$msg);
		}
	}
	if(!function_exists('mneq')){
		function mneq($keyword,$src,$msg='failure not match'){
			\testman\Assert::mneq($keyword,$src,$msg);
		}
	}
	if(!function_exists('test_map_url')){
		function test_map_url($map_name){
			$args = func_get_args();
			array_shift($args);
			return \testman\Assert::test_map_url($map_name,$args);
		}
	}	
	
	// include
	$debug = debug_backtrace(false);
	if(sizeof($debug) > 1 || (isset($debug[0]['file']) && substr($debug[0]['file'],-5) != '.phar')){
		return;
	}
	
	/**
	 * 基本の動作
	 */
	\testman\Args::init();
	
	$testdir = realpath(\testman\Args::value(getcwd().'/test'));
	if($testdir === false){
		die(\testman\Args::value().' found'.PHP_EOL);
	}
	if(is_file($f=getcwd().'/bootstrap.php') || is_file($f=getcwd().'/vendor/autoload.php')){
		ob_start();
			include_once($f);
		ob_end_clean();
	}
	\testman\Std::println('testman [VERSION] (PHP '.phpversion().')'); // version
	
	if(\testman\Args::opt('help')){
		\testman\Std::println('Usage: php '.basename(__FILE__).' [options] [dir/ | dir/file.php]');
		\testman\Std::println();
		\testman\Std::println('Options:');
		\testman\Std::println('  --coverage <file>  Generate code coverage report in XML format.');
		\testman\Std::println('  --output <file>    Log test execution in XML format to file');
		\testman\Std::println('  --list [keyword]  List test files');
		exit;
	}
	\testman\Runner::init($testdir);
	
	if(($keyword = \testman\Args::opt('list',false)) !== false){
		$cwd = getcwd().DIRECTORY_SEPARATOR;

		$summary = function($file){
			$src = file_get_contents($file);
			$summary = '';
				
			if(preg_match('/\/\*.+?\*\//s',$src,$m)){
				list($summary) = explode(PHP_EOL,trim(
					preg_replace('/@.+/','',
						preg_replace("/^[\s]*\*[\s]{0,1}/m","",str_replace(array("/"."**","*"."/"),"",$m[0]))
					)
				));
			}
			return $summary;
		};
		$len = 8;
		$test_list = array();
		foreach(\testman\Runner::get_list($testdir) as $test_path){
			if($keyword === true || strpos($test,$keyword) !== false){
				$name = str_replace($cwd,'',$test_path);
				
				if($len < strlen($name)){
					$len = strlen($name);
				}
				$test_list[$name] = $test_path;
			}
		}
		
		foreach($test_list as $name => $path){
			\testman\Std::println('  '.str_pad($name,$len).' : '.$summary($path));
		}
		exit;
	}

	foreach(array('coverage','output','coverage-dir') as $k){
		if(($v = \testman\Args::opt($k,null)) !== null && !is_bool($v)){
			\testman\Conf::set($k,$v);
		}
	}
	\testman\Coverage::start(\testman\Conf::get('coverage'),\testman\Conf::get('coverage-dir'));
	\testman\Runner::start($testdir);
	
	if(\testman\Coverage::stop()){
		\testman\Coverage::output(true);
	}
	if(\testman\Conf::has('output')){
		\testman\Std::println_primary('Written Result:   '.\testman\Runner::output(\testman\Conf::get('output')).' ');
	}
}
