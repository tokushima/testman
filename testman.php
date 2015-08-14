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
		 * 設定ファイルのパス
		 * @param string $name
		 * @throws \InvalidArgumentException
		 * @return string
		 */
		public static function settings_path($name){
			$dir = dirname(__FILE__);
			if(strpos($dir,'phar://') === 0){
				$dir = str_replace('phar://','',dirname($dir));
			}
			if(!is_dir($dir)){
				throw new \InvalidArgumentException('not found '.$dir);
			}
			return $dir.'/'.$name;
		}
		/**
		 * 設定ファイル/ディレクトリが存在するか
		 * @param string $name
		 * @return Ambigous <NULL, string>|NULL
		 */				
		public static function has_settings($name){
			try{
				$path = self::settings_path('testman.'.$name);
				return (is_file($path) || is_dir($path) ? $path : null);
			}catch(\InvalidArgumentException $e){
			}
			return null;
		}
	}
	class Resource{
		public static function path($file){
			$dir = \testman\Conf::has_settings('resources');
			if(is_file($f=$dir.'/'.$file)){
				return realpath($f);
			}
			throw new \testman\NotFoundException($file.' not found');
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
	}
	class Finder{
		/**
		 * テスト対象ファイルを探す
		 * @param string $test_dir
		 * @throws \InvalidArgumentException
		 * @return stirng[]
		 */
		public static function get_list($test_dir){
			$test_list = array();
				
			if(is_dir($test_dir)){
				foreach(new \RegexIterator(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($test_dir,
						\FilesystemIterator::CURRENT_AS_FILEINFO|\FilesystemIterator::SKIP_DOTS|\FilesystemIterator::UNIX_PATHS
				),\RecursiveIteratorIterator::SELF_FIRST),'/\.php$/') as $f){
					if(!preg_match('/\/[\._]/',$f->getPathname()) && strpos($f->getPathname(),basename(__FILE__,'.php').'.') === false){
						$test_list[$f->getPathname()] = true;
					}
				}
			}else if(is_file($test_dir)){
				$test_list[realpath($test_dir)] = true;
			}else{
				throw new \InvalidArgumentException($test_dir.' not fond');
			}
			ksort($test_list);
				
			return array_keys($test_list);
		}
		/**
		 * サマリ一覧
		 * @param unknown $testdir
		 * @param string $keyword
		 * @return Ambigous <string, multitype:>|multitype:unknown
		 */
		public static function summary_list($testdir,$keyword=''){
			$cwd = getcwd().DIRECTORY_SEPARATOR;
		
			$summary = function($src){
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
			foreach(self::get_list($testdir) as $test_path){
				$src = file_get_contents($test_path);
								
				if($keyword === true || strpos($test_path,$keyword) !== false || strpos($src,$keyword) !== false){
					$name = str_replace($cwd,'',$test_path);
		
					if($len < strlen($name)){
						$len = strlen($name);
					}
					$test_list[$name] = array('path'=>$test_path,'summary'=>$summary($src));
				}
			}
			foreach($test_list as $name => $info){
				\testman\Std::println('  '.str_pad($name,$len).' : '.$info['summary']);
			}
			\testman\Std::println();
			
			return $test_list;
		}
		/**
		 * setupの説明
		 * @param string $testdir
		 * @param boolean $source
		 */
		public static function setup_info($dir,$source=false){
			$dir = realpath($dir);
			if($dir === false){
				throw new \InvalidArgumentException($dir.' not found');
			}
			list($var_types,$inc_list,$target_dir) = self::setup_teardown_files($dir, true);
			
			$summary_list = array();
			foreach($inc_list as $inc){
				$summary_list[] = empty($inc['summary']) ? '[NONE]' : $inc['summary'];
			}
			$nlen = $tlen = 0;
			foreach($var_types as $name => $type){
				if($nlen < strlen($name)){
					$nlen = strlen($name);
				}
				if($tlen < strlen($type['type'])){
					$tlen = strlen($type['type']);
				}
			}
			\testman\Std::println_warning('Dir:');
			\testman\Std::println('  '.\testman\Runner::short_name($target_dir));
			\testman\Std::println();
			
			\testman\Std::println_warning('Summary:');
			\testman\Std::println('  '.implode(' > ',$summary_list));
			\testman\Std::println();
			
			ksort($var_types);
			
			\testman\Std::println_warning('Vars:');
			foreach($var_types as $name => $type){
				\testman\Std::println('  '.str_pad($type['type'],$tlen).' $'.str_pad($name,$nlen).' : '.$type['desc']);
			}
			\testman\Std::println();
			
			if($source){
				\testman\Std::println_warning('Source:');
				\testman\Std::println();
				
				foreach($inc_list as $inc){
					\testman\Std::println_white('// '.str_repeat('-', 77));
					\testman\Std::println_white('// path:    '.$inc['path']);
					\testman\Std::println_white('// summary: '.$inc['summary']);
					\testman\Std::println_white('// '.str_repeat('-', 77));
					
					$src = file_get_contents($inc['path']);
					$src = trim(preg_replace('/^[\s]*<\?php/','',$src));
					$src = preg_replace('/\/\*.+?\*\//s','',$src);
					
					\testman\Std::println_info($src);
					\testman\Std::println();
				}
			}
			\testman\Std::println();
		}
		/**
		 * setup/teardownを探す
		 * @param string $dir
		 * @param boolean $is_setup
		 * @return string[]
		 */
		public static function setup_teardown_files($testdir,$is_setup){
			if(is_dir($dir=$testdir) || is_dir($dir=dirname($testdir))){
				$file = ($is_setup) ? '__setup__.php' : '__teardown__.php';
				$inc_list = array();
				$var_types = array();
				$target_dir = $dir;
		
				while(strlen($dir) >= strlen(getcwd())){
					if(is_file($f=($dir.'/'.$file))){
						$varnames = array();
						$summary = null;
						
						if($is_setup && preg_match('/\/\*.+?\*\//s',file_get_contents($f),$_m)){
							$desc = preg_replace("/^[\s]*\*[\s]{0,1}/m","",str_replace(array("/"."**","*"."/"),"",$_m[0]));
							
							if(preg_match_all('/@.+/',$desc,$_as)){
								foreach($_as[0] as $_m){
									if(preg_match("/@var\s+([^\s]+)\s+\\$(\w+)(.*)/",$_m,$_p)){
										$var_types[$_p[2]]['type'] = $_p[1];
										if(!isset($var_types[$_p[2]]['desc']) || empty($var_types[$_p[2]]['desc'])){
											$var_types[$_p[2]]['desc'] = trim($_p[3]);
										}
										$varnames[] = $_p[2];
									}else if(preg_match("/@var\s+\\$(\w+)(.*)/",$_m,$_p)){
										$var_types[$_p[1]]['type'] = 'string';
										if(!isset($var_types[$_p[1]]['desc']) || empty($var_types[$_p[1]]['desc'])){
											$var_types[$_p[1]]['desc'] = trim($_p[2]);
										}
										$varnames[] = $_p[1];
									}
								}
							}
							list($summary) = explode(PHP_EOL,trim(preg_replace('/@.+/','',$desc)));
						}
						$inc_list[] = array('path'=>$f,'vars'=>$varnames,'summary'=>$summary);
					}
					$dir = dirname($dir);
				}
				if($is_setup){
					krsort($inc_list);
				}
				return array($var_types,$inc_list,$target_dir);
			}
			return array(array(),array(),'');
		}
	}
	class DefinedVarsRequireException extends \Exception{
	}
	class DefinedVarsInvalidTypeException extends \Exception{
	}
	class Runner{
		static private $resultset = array();
		static private $current_test;
		static private $start = false;
		static private $vars = array();
		
		/**
		 * 現在実行しているテスト
		 */
		public static function current(){
			return self::$current_test;
		}

		private static function trim_msg($msg,$len){
			if(strlen($msg) > $len){
				return mb_substr($msg,0,ceil($len/2)).' .. '.mb_substr($msg,ceil($len/2)*-1);
			}
			return $msg;
		}
		/**
		 * 対象のテスト群を実行する
		 * @param string $testdir
		 */
		public static function start($testdir){
			if(self::$start){
				return self::$resultset;
			}
			if(!is_dir($testdir) && !is_file($testdir)){
				throw new \InvalidArgumentException($testdir.' not found');
			}
			self::$start = true;
			
			try{
				for($i=0;$i<5;$i++){
					\testman\Std::println();
				}
				\testman\Std::cur(-5,0);
				
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
					if('neutral' == mb_language()){
						mb_language('Japanese');
					}
					mb_internal_encoding('UTF-8');
				}
				if(function_exists('opcache_reset')){
					opcache_reset();
				}
				clearstatcache(true);
				
				set_error_handler(function($n,$s,$f,$l){
					throw new \ErrorException($s,0,$n,$f,$l);
				});
				
				if(null !== ($dir = \testman\Conf::has_settings('lib'))){
					$dir = realpath($dir);
					
					spl_autoload_register(function($class) use ($dir){
						$cp = str_replace('\\','/',(($class[0] == '\\') ? substr($class,1) : $class));
	
						if(strpos($cp,'test/') === 0 && is_file($f=($dir.'/'.substr($cp,5).'.php'))){
							require_once($f);
		
							if(class_exists($class,false) || interface_exists($class,false) || trait_exists($class,false)){
								return true;
							}
						}
						return false;
					},true,false);
				}
				
				if(null !== ($f = \testman\Conf::has_settings('settings.php'))){
					$msg = 'Setting '.$f;
					\testman\Std::p($msg,'36');				
					include_once($f);
					\testman\Std::bs(strlen($msg));
				}
				$testdir = realpath($testdir);
				$success = $fail = $exception = $exe_time = $use_memory = 0;
				
				$msg = 'Finding '.$testdir;
				\testman\Std::p($msg,'36');
				$test_list = \testman\Finder::get_list($testdir);
				\testman\Std::bs(strlen($msg));
				
				if(null !== ($f = \testman\Conf::has_settings('fixture.php'))){
					$msg = 'Init: '.$f;
					\testman\Std::p($msg,'36');
						include_once($f);
					\testman\Std::bs(strlen($msg));
				}
				
				$start_time = microtime(true);
				$start_mem = round(number_format((memory_get_usage() / 1024 / 1024),3),4);				
				\testman\Coverage::start(\testman\Conf::get('coverage'),\testman\Conf::get('coverage-dir'));
				
				$ey = $cnt = 0;				
				$testcnt = sizeof($test_list);
				foreach($test_list as $test_path){
					$cnt++;

					$msg = 'Running.. ('.($cnt.'/'.$testcnt).') '.self::trim_msg(\testman\Runner::short_name($test_path),80).' ';
					\testman\Std::p($msg,33);
						list($test_name,$res) = \testman\Runner::exec($test_path);
					\testman\Std::bs(strlen($msg));
					
					if($res[0] != 1){
						if($ey == 0){
							$ey = 2;
							
							$msg = 'Failure:';
							\testman\Std::cur($ey,0);
							\testman\Std::p($msg,31);
							\testman\Std::cur($ey*-1,strlen($msg) * -1);
						}
						$ey++;
						
						$msg = '  '.self::trim_msg($test_name,80).':'.$res[3];
						\testman\Std::cur($ey,0);
						\testman\Std::p($msg.PHP_EOL.PHP_EOL.PHP_EOL,31);
						\testman\Std::cur(($ey + 3) * -1,strlen($msg) * -1);
					}
				}
				if($ey > 0){
					for($a=0;$a<=$ey;$a++){
						\testman\Std::cur(1,0);
						\testman\Std::line_clear();
					}
					\testman\Std::cur($ey*-1,0);
				}
			
				$exe_time = round((microtime(true) - (float)$start_time),4);
				$use_memory = round(number_format((memory_get_usage() / 1024 / 1024),3),4) - $start_mem;
				
				\testman\Std::println_warning('Results:');
				
				$tab = '   ';
				foreach(self::$resultset as $testfile => $info){
					switch($info[0]){
						case 1:
							$success++;
							break;
						case -1:
							$fail++;
							list(,$time,$file,$line,$msg,$r1,$r2,$has) = $info;
								
							\testman\Std::println();
							\testman\Std::println_primary(' '.$testfile);
							\testman\Std::println_danger('  ['.$line.']: '.$msg);
								
							if($has){
								$expectation = ' expect ';
								\testman\Std::println_white($tab.str_repeat('-',(73-strlen($expectation))).$expectation.str_repeat('-',3));
								ob_start();
									var_dump($r1);
								$diff1 = ob_get_clean();
								\testman\Std::println($tab.str_replace(PHP_EOL,PHP_EOL.$tab,$diff1));
			
								$result = ' result ';
								\testman\Std::println_white($tab.str_repeat('-',(73-strlen($result))).$result.str_repeat('-',3));
								ob_start();
									var_dump($r2);
								$diff2 = ob_get_clean();
								\testman\Std::println($tab.str_replace(PHP_EOL,PHP_EOL.$tab,$diff2));
							}
							break;
						case -2:
							$exception++;
							list(,$time,$file,$line,$msg) = $info;
							
							$msgarr = explode(PHP_EOL,$msg);
							$summary = array_shift($msgarr);
							
							\testman\Std::println();
							\testman\Std::println_primary(' '.$testfile);
							\testman\Std::println_danger('  ['.$line.']: '.$summary);
							\testman\Std::println($tab.implode(PHP_EOL.$tab,$msgarr));
							break;
					}
				}
				\testman\Std::println(str_repeat('=',80));
				\testman\Std::println_info(sprintf('success %d, failures %d, errors %d (%.05f sec / %s MByte)',$success,$fail,$exception,$exe_time,$use_memory));
				\testman\Std::println();
				
				if(\testman\Conf::has('output')){
					\testman\Std::println_primary('Written Result:   '.self::output(\testman\Conf::get('output')).' ');
				}
				if(\testman\Coverage::stop()){
					\testman\Coverage::output(true);
				}
				\testman\Std::println();
			}catch(\Exception $e){
				\testman\Std::println_danger(PHP_EOL.PHP_EOL.'Failure:'.PHP_EOL.PHP_EOL.$e->getMessage().PHP_EOL.$e->getTraceAsString());
			}
			return self::$resultset;
		}
		
		private static function output($output){
			if(!is_dir(dirname($output))){
				mkdir(dirname($output),0777,true);
			}
			$xml = new \SimpleXMLElement('<testsuites></testsuites>');
			$get_testsuite = function($dir,&$testsuite) use($xml){
				if(empty($testsuite)){
					$testsuite = $xml->addChild('testsuite');
					$testsuite->addAttribute('name',$dir);
				}
				return $testsuite;
			};
		
			$list = array();
			foreach(self::$resultset as $file => $info){
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
			$xml->addAttribute('tests',sizeof(self::$resultset));
			$xml->addAttribute('failures',$failures);
			$xml->addAttribute('errors',$errors);
			$xml->addAttribute('time',$times);
			$xml->addAttribute('create_date',date('Y/m/d H:i:s'));
			$xml->addChild('system-out');
			$xml->addChild('system-err');
		
			file_put_contents($output,$xml->asXML());
				
			return realpath($output);
		}
		private static function exec_include($_is_setup,$_inc,$_var_types){
			extract(self::$vars);
			include($_inc['path']);
			
			if($_is_setup){
				$_getvars = get_defined_vars();
				
				foreach($_inc['vars'] as $k){
					$type = $_var_types[$k]['type'];
					
					if(!isset($_getvars[$k])){
						throw new \testman\DefinedVarsRequireException($k.' required');
					}
					switch($type){
						case 'string':
							if(!is_string($_getvars[$k])){
								throw new \testman\DefinedVarsInvalidTypeException($k.' must be an '.$type);
							}
							break;
						case 'integer':
							if(!is_int($_getvars[$k])){
								throw new \testman\DefinedVarsInvalidTypeException($k.' must be an '.$type);
							}
							break;
						case 'float':
							if(!is_float($_getvars[$k])){
								throw new \testman\DefinedVarsInvalidTypeException($k.' must be an '.$type);
							}
							break;
						case 'boolean':
							if(!is_bool($_getvars[$k])){
								throw new \testman\DefinedVarsInvalidTypeException($k.' must be an '.$type);
							}
							break;
						default:
							if(!is_object($_getvars[$k])){
								throw new \testman\DefinedVarsInvalidTypeException($k.' must be an '.$type);								
							}
							if(!($_getvars[$k] instanceof $type)){
								throw new \testman\DefinedVarsInvalidTypeException($k.'('.get_class($_getvars[$k]).') must be an '.$type);
							}
					}
					self::$vars[$k] = $_getvars[$k];
				} 
			}
		}
		private static function exec_setup_teardown($test_file,$is_setup){
			list($var_types,$inc_list,$target_dir) = \testman\Finder::setup_teardown_files($test_file,$is_setup);
			
			foreach($inc_list as $inc){
				self::exec_include($is_setup,$inc,$var_types);
			}
		}
		public static function short_name($test_file){
			return str_replace(getcwd().DIRECTORY_SEPARATOR,'',$test_file);
		}
		private static function exec($test_file){
			self::$vars = array();
			self::$current_test = $test_file;
			
			try{
				ob_start();
					self::exec_setup_teardown($test_file,true);
					$test_exec_start_time = microtime(true);
				
					foreach(self::$vars as $k => $v){
						$$k = $v;
					}
					include($test_file);
				$rtn = ob_get_clean();
				
				if(preg_match('/(Parse|Fatal) error:.+/',$rtn,$m)){
					$err = (preg_match('/syntax error.+code on line\s*(\d+)/',$rtn,$line) ?
							'Parse error: syntax error '.$test_file.' code on line '.$line[1]
							: $m[0]);
					throw new \RuntimeException($err);
				}
				$res = array(1,(round(microtime(true) - $test_exec_start_time,3)));
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
			$test_name = self::short_name($test_file);
			self::exec_setup_teardown($test_file,false);
			self::$resultset[$test_name] = $res;
			return array($test_name,$res);
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
					if(!is_dir($d = dirname($output_xml))){
						mkdir($d,0777,true);
					}
					file_put_contents($output_xml,'');
					self::$db = realpath($output_xml);
					
					$target_list_db = self::$db.'.target';
					$tmp_db = self::$db.'.tmp';					
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
								self::$result[$filename] = array('covered_line_status'=>array(),'uncovered_line_status'=>array(),'test'=>1);
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
						self::$result[$filename] = array('covered_line'=>array(),'uncovered_line'=>array(),'test'=>0);
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
		 */
		public static function output($written_xml){
			\testman\Std::println();
			\testman\Std::println_warning('Coverage: ');
			
			if($written_xml){
				$xml = new \SimpleXMLElement('<coverage></coverage>');
			}

			$total_covered = $total_lines = 0;
			foreach(self::get() as $filename => $resultset){
				$covered = count($resultset['covered_line']);
				$uncovered = count($resultset['uncovered_line']);
				$covered = (($resultset['test'] == 1) ? ceil($covered / ($covered + $uncovered) * 100) : 0);
				
				$total_lines += $covered + $uncovered;
				$total_covered += $covered;
				
				if(isset($xml)){
					$f = $xml->addChild('file');
					$f->addAttribute('name',str_replace(getcwd().DIRECTORY_SEPARATOR,'',$filename));
					$f->addAttribute('covered',$covered);
					$f->addAttribute('modify_date',date('Y/m/d H:i:s',filemtime($filename)));
					$f->addChild('covered_lines',implode(',',$resultset['covered_line']));
					$f->addChild('uncovered_lines',implode(',',$resultset['uncovered_line']));
					$f->addAttribute('test',($resultset['test'] == 1) ? 'true' : 'false');
				}
				
				$msg = sprintf(' %3d%% %s',$covered,$filename);
				if($covered == 100){
					\testman\Std::println_success($msg);
				}else if($covered > 50){
					\testman\Std::println_warning($msg);
				}else{
					\testman\Std::println_danger($msg);						
				}
			}
			$covered_sum = ($total_covered == 0) ? 0 : ceil($total_covered/$total_lines*100);
						
			\testman\Std::println(str_repeat('-',70));
			\testman\Std::println_info(sprintf(' Covered %s%%',$covered_sum));
			
			if(isset($xml)){
				$xml->addAttribute('create_date',date('Y/m/d H:i:s'));
				$xml->addAttribute('covered',$covered_sum);
				$xml->addAttribute('lines',$total_lines);
				$xml->addAttribute('covered_lines',$total_covered);

				$save_path = realpath(self::$db);
				if(!is_dir(dirname($save_path))){
					mkdir(dirname($save_path),0777,true);
				}				
				$dom = new \DOMDocument('1.0','utf-8');
				$node = $dom->importNode(dom_import_simplexml($xml), true);
				$dom->appendChild($node);
				$dom->preserveWhiteSpace = false;
				$dom->formatOutput = true;
				$dom->save($save_path);
				
				\testman\Std::println_primary(PHP_EOL.'Written Coverage: '.$save_path);
			}
		}
		
		public static function load($xml_file){
			$xml = \testman\Xml::extract(file_get_contents($xml_file),'coverage');
			$create_date = strtotime($xml->in_attr('create_date'));
			
			self::$result = array();
			foreach($xml->find('file') as $file){
				self::$result[$file->in_attr('name')] = array(
					'covered_line'=>explode(',',$file->find_get('covered_lines')->value()),
					'uncovered_line'=>explode(',',$file->find_get('uncovered_lines')->value()),
					'test'=>(($file->in_attr('test') == 'true') ? 1 : 0),
				);
			}
			return $create_date;
		}
		
		public static function output_source($source_file,$coverage_date){
			if(!is_file($source_file)){
				throw new \testman\NotFoundException('ない');
			}
			$source_file = realpath($source_file);
			$path = str_replace(getcwd().DIRECTORY_SEPARATOR,'',$source_file);
			
			if(!isset(self::$result[$path])){
				throw new \testman\NotFoundException('かアレッジない');
			}
			if(filemtime($source_file) > strtotime($coverage_date)){
				throw new \testman\NotFoundException('更新されちゃってる');
			}
			$source_list = explode(PHP_EOL,file_get_contents($source_file));
			
			$covered = count(self::$result[$path]['covered_line']);
			$uncovered = count(self::$result[$path]['uncovered_line']);
			$covered = ((self::$result[$path]['test'] == 1) ? ceil($covered / ($covered + $uncovered) * 100) : 0);
			
			\testman\Std::println();
			\testman\Std::println_warning('Coverage: ');
			
			
			$msg = sprintf('  %d%% %s',$covered,$path);			
			if($covered == 100){
				\testman\Std::println_success($msg);
			}else if($covered > 50){
				\testman\Std::println_warning($msg);
			}else{
				\testman\Std::println_danger($msg);
			}
			
			\testman\Std::println();
			
			$tab = strlen(sizeof($source_list));
			foreach($source_list as $no => $line){
				$i = $no + 1;
				$msg = sprintf('  %'.$tab.'s %s',$i,$line);
				
				if(self::$result[$path]['test'] !== 1){
					\testman\Std::println($msg);
				}else if(in_array($i,self::$result[$path]['covered_line'])){
					\testman\Std::println_success($msg);
				}else if(in_array($i,self::$result[$path]['uncovered_line'])){
					\testman\Std::println_danger($msg);
				}else{
					\testman\Std::println_white($msg);
				}
			}
			\testman\Std::println();
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
		 * 対象の件数
		 * @param string $name
		 * @param integer $offset
		 * @param integer $length
		 * @return number
		 */
		public function find_count($name,$offset=0,$length=0){
			$cnt = 0;
			
			foreach($this->find($name,$offset,$length) as $x){
				$cnt++;
			}
			return $cnt;
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
			if(!empty($name)){
				$names = explode('/',$name,2);
				$name = $names[0];
			}
			if(self::find_extract($x,$plain,$name)){
				if(isset($names[1])){
					try{
						return $x->find_get($names[1]);
					}catch(\testman\NotFoundException $e){
						throw $e;
					}
				}else{
					return $x;
				}
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
			if(!preg_match("/<(".$qname.")([\s][^>]*?)>|<(".$qname.")>|<(".$qname.")\/>/is",$plain,$parse,PREG_OFFSET_CAPTURE)){
				return false;
			}
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
	class Util{
		/**
		 * 絶対パスを返す
		 * @param string $a
		 * @param string $b
		 * @return string
		 */
		public static function path_absolute($a,$b){
			if($b === '' || $b === null) return $a;
			if($a === '' || $a === null || preg_match("/^[a-zA-Z]+:/",$b)) return $b;
			if(preg_match("/^[\w]+\:\/\/[^\/]+/",$a,$h)){
				$a = preg_replace("/^(.+?)[".(($b[0] === '#') ? '#' : "#\?")."].*$/","\\1",$a);
				if($b[0] == '#' || $b[0] == '?') return $a.$b;
				if(substr($a,-1) != '/') $b = (substr($b,0,2) == './') ? '.'.$b : (($b[0] != '.' && $b[0] != '/') ? '../'.$b : $b);
				if($b[0] == '/' && isset($h[0])) return $h[0].$b;
			}else if($b[0] == '/'){
				return $b;
			}
			$p = array(
					array('://','/./','//'),
					array('#R#','/','/'),
					array("/^\/(.+)$/","/^(\w):\/(.+)$/"),
					array("#T#\\1","\\1#W#\\2",''),
					array('#R#','#W#','#T#'),
					array('://',':/','/')
			);
			$a = preg_replace($p[2],$p[3],str_replace($p[0],$p[1],$a));
			$b = preg_replace($p[2],$p[3],str_replace($p[0],$p[1],$b));
			$d = $t = $r = '';
			if(strpos($a,'#R#')){
				list($r) = explode('/',$a,2);
				$a = substr($a,strlen($r));
				$b = str_replace('#T#','',$b);
			}
			$al = preg_split("/\//",$a,-1,PREG_SPLIT_NO_EMPTY);
			$bl = preg_split("/\//",$b,-1,PREG_SPLIT_NO_EMPTY);
		
			for($i=0;$i<sizeof($al)-substr_count($b,'../');$i++){
				if($al[$i] != '.' && $al[$i] != '..') $d .= $al[$i].'/';
			}
			for($i=0;$i<sizeof($bl);$i++){
				if($bl[$i] != '.' && $bl[$i] != '..') $t .= '/'.$bl[$i];
			}
			$t = (!empty($d)) ? substr($t,1) : $t;
			$d = (!empty($d) && $d[0] != '/' && substr($d,0,3) != '#T#' && !strpos($d,'#W#')) ? '/'.$d : $d;
			return str_replace($p[4],$p[5],$r.$d.$t);
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
	
		private $user;
		private $password;
	
		private $raw;
	
		public function __construct($agent=null,$timeout=30,$redirect_max=20){
			$this->agent = $agent;
			$this->timeout = (int)$timeout;
			$this->redirect_max = (int)$redirect_max;
		}
		/**
		 * 最大リダイレクト回数を設定
		 * @param integer $redirect_max
		 */
		public function redirect_max($redirect_max){
			$this->redirect_max = (integer)$redirect_max;
			return $this;
		}
		/**
		 * タイムアウト時間を設定
		 * @param integer $timeout
		 * @return $this
		 */
		public function timeout($timeout){
			$this->timeout = (int)$timeout;
			return $this;
		}
		/**
		 * ユーザエージェントを設定
		 * @param string $agent
		 * @return $this
		 */
		public function agent($agent){
			$this->agent = $agent;
			return $this;
		}
		/**
		 * Basic認証
		 * @param string $user ユーザ名
		 * @param string $password パスワード
		 * @return $this
		 */
		public function basic($user,$password){
			$this->user = $user;
			$this->password = $password;
			return $this;
		}
		public function __toString(){
			return $this->body();
		}
		/**
		 * ヘッダを設定
		 * @param string $key
		 * @param string $value
		 * @return $this
		 */
		public function header($key,$value=null){
			$this->request_header[$key] = $value;
			return $this;
		}
		/**
		 * クエリを設定
		 * @param string $key
		 * @param string $value
		 * @return $this
		 */
		public function vars($key,$value=null){
			if(is_bool($value)) $value = ($value) ? 'true' : 'false';
			$this->request_vars[$key] = $value;
			if(isset($this->request_file_vars[$key])) unset($this->request_file_vars[$key]);
			return $this;
		}
		/**
		 * クエリにファイルを設定
		 * @param string $key
		 * @param string $filename
		 * @return $this
		 */
		public function file_vars($key,$filename){
			$this->request_file_vars[$key] = $filename;
			if(isset($this->request_vars[$key])) unset($this->request_vars[$key]);
			return $this;
		}
		/**
		 * クエリが設定されているか
		 * @param string $key
		 * @return $this
		 */
		public function has_vars($key){
			return (array_key_exists($key,$this->request_vars) || array_key_exists($key,$this->request_file_vars));
		}
		/**
		 * cURL 転送用オプションを設定する
		 * @param string $key
		 * @param mixed $value
		 * @return $this
		 */
		public function setopt($key,$value){
			if(!isset($this->resource)) $this->resource = curl_init();
			curl_setopt($this->resource,$key,$value);
			return $this;
		}
		/**
		 * 結果のヘッダを取得
		 * @return string
		 */
		public function head(){
			return $this->head;
		}
		/**
		 * 結果の本文を取得
		 * @return string
		 */
		public function body(){
			return ($this->body === null || is_bool($this->body)) ? '' : $this->body;
		}
		/**
		 * 結果のURLを取得
		 * @return string
		 */
		public function url(){
			return $this->url;
		}
		/**
		 * 結果のステータスを取得
		 * @return integer
		 */
		public function status(){
			return empty($this->status) ? null : (int)$this->status;
		}
		/**
		 * HEADリクエスト
		 * @param string $url
		 * @return $this
		 */
		public function do_head($url){
			return $this->request('HEAD',$url);
		}
		/**
		 * PUTリクエスト
		 * @param string $url
		 * @return $this
		 */
		public function do_put($url){
			return $this->request('PUT',$url);
		}
		/**
		 * DELETEリクエスト
		 * @param string $url
		 * @return $this
		 */
		public function do_delete($url){
			return $this->request('DELETE',$url);
		}
		/**
		 * GETリクエスト
		 * @param string $url
		 * @return $this
		 */
		public function do_get($url){
			return $this->request('GET',$url);
		}
		/**
		 * POSTリクエスト
		 * @param string $url
		 * @return $this
		 */
		public function do_post($url){
			return $this->request('POST',$url);
		}
		/**
		 * POSTリクエスト(RAW)
		 * @param string $url
		 * @return $this
		 */
		public function do_raw($url,$value){
			$this->raw = $value;
			return $this->request('RAW',$url);
		}
		/**
		 * POSTリクエスト(JSON)
		 * @param string $url
		 * @return $this
		 */
		public function do_json($url){
			$this->header('Content-type','application/json');
			return $this->do_raw($url,json_encode($this->request_vars));
		}
		/**
		 * GETリクエストでダウンロードする
		 * @param string $url
		 * @param string $filename
		 */
		public function do_download($url,$filename){
			return $this->request('GET',$url,$filename);
		}
		/**
		 * POSTリクエストでダウンロードする
		 * @param string $url
		 * @param string $filename
		 */
		public function do_post_download($url,$filename){
			return $this->request('POST',$url,$filename);
		}
		/**
		 * ヘッダ情報をハッシュで取得する
		 * @return string{}
		 */
		public function explode_head(){
			$result = array();
			foreach(explode("\n",$this->head) as $h){
				if(preg_match("/^(.+?):(.+)$/",$h,$match)) $result[trim($match[1])] = trim($match[2]);
			}
			return $result;
		}
		/**
		 * ヘッダデータを書き込む処理
		 * @param resource $resource
		 * @param string $data
		 * @return number
		 */
		public function callback_head($resource,$data){
			$this->head .= $data;
			return strlen($data);
		}
		/**
		 * データを書き込む処理
		 * @param resource $resource
		 * @param string $data
		 * @return number
		 */
		public function callback_body($resource,$data){
			$this->body .= $data;
			return strlen($data);
		}
		private function request($method,$url,$download_path=null){
			if(!isset($this->resource)) $this->resource = curl_init();
			$url_info = parse_url($url);
			$cookie_base_domain = (isset($url_info['host']) ? $url_info['host'] : '').(isset($url_info['path']) ? $url_info['path'] : '');

			// set coverage query
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
				case 'RAW':
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
				case 'RAW':
					$this->request_header['Content-Type'] = 'text/plain';
					curl_setopt($this->resource,CURLOPT_POSTFIELDS,$this->raw);
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
	
			if(!empty($this->user)){
				curl_setopt($this->resource,CURLOPT_USERPWD,$this->user.':'.$this->password);
			}
			if(!isset($this->request_header['Expect'])){
				$this->request_header['Expect'] = null;
			}
			if(!isset($this->request_header['Cookie'])){
				$cookies = '';
				foreach($this->cookie as $domain => $cookie_value){
					if(strpos($cookie_base_domain,$domain) === 0 || strpos($cookie_base_domain,(($domain[0] == '.') ? $domain : '.'.$domain)) !== false){
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
	
			curl_setopt($this->resource,CURLOPT_HTTPHEADER,
				array_map(
					function($k,$v){
						return $k.': '.$v;
					}
					,array_keys($this->request_header)
					,$this->request_header
				)
			);
			curl_setopt($this->resource,CURLOPT_HEADERFUNCTION,array($this,'callback_head'));
	
			if(empty($download_path)){
				curl_setopt($this->resource,CURLOPT_WRITEFUNCTION,array($this,'callback_body'));
			}else{
				if(strpos($download_path,'://') === false && !is_dir(dirname($download_path))){
					mkdir(dirname($download_path),0777,true);
				}
				$fp = fopen($download_path,'wb');
					
				curl_setopt($this->resource,CURLOPT_WRITEFUNCTION,function($resource,$data) use(&$fp){
					if($fp) fwrite($fp,$data);
					return strlen($data);
				});
			}
			$this->request_header = $this->request_vars = array();
			$this->head = $this->body = $this->raw = '';
			curl_exec($this->resource);
			
			if(!empty($download_path) && $fp){
				fclose($fp);
			}
			if(($err_code = curl_errno($this->resource)) > 0){
				if($err_code == 47 || $err_code == 52) return $this;
				throw new \RuntimeException($err_code.': '.curl_error($this->resource));
			}
			$this->url = curl_getinfo($this->resource,CURLINFO_EFFECTIVE_URL);
			$this->status = curl_getinfo($this->resource,CURLINFO_HTTP_CODE);
	
			// remove coverage query
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
					$cookie_domain = $cookie_base_domain;
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
					$cookie_domain = substr(\testman\Util::path_absolute('http://'.$cookie_domain,$cookie_path),7);
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
							return $this->request('GET',trim(\testman\Util::path_absolute($url,$redirect_url[1])),$download_path);
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
		public function xml($name=null){
			try{
				return \testman\Xml::extract($this->body(),$name);
			}catch(\testman\NotFoundException $e){
				throw new \testman\NotFoundException($e->getMessage().': '.substr($this->body(),0,100).((strlen($this->body()) > 100) ? '..' : ''));
			}
		}
		/**
		 * bodyを解析し配列として返す
		 * @param string $name
		 * @param string $delimiter
		 * @return mixed{}
		 */
		public function json($name=null,$delimiter='/'){
			$array = json_decode($this->body(),true);
			
			if($array === false){
				throw new \testman\NotFoundException('Invalid data: '.': '.substr($this->body(),0,100).((strlen($this->body()) > 100) ? '..' : ''));
			}
			if(empty($name)){
				return $array;
			}
			$names = explode($delimiter,$name);
			foreach($names as $key){
				if(array_key_exists($key,$array)){
					$array = $array[$key];
				}else{
					throw new \testman\NotFoundException($name.' not found: '.': '.substr($this->body(),0,100).((strlen($this->body()) > 100) ? '..' : ''));
				}
			}
			return $array;
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
		private static $stdout = true;
		
		/**
		 * 標準出力に表示するか
		 * @param boolean $bool
		 */
		public static function disp($bool){
			self::$stdout = $bool;
		}
		/**
		 * 色付きでプリント
		 * @param string $msg
		 */
		public static function p($msg,$color='0'){
			if(self::$stdout){
				print("\033[".$color."m".$msg."\033[0m");
			}
		}
		/**
		 * カーソルを移動
		 * @param integer $num
		 */
		public static function cur($up_down,$left_right){
			if(!empty($up_down)){
				if($up_down < 0){
					print("\033[".($up_down*-1)."A");
				}else{
					print("\033[".$up_down."B");	
				}
			}
			if(!empty($left_right)){
				if($left_right < 0){
					print("\033[".($left_right*-1)."D");
				}else{
					print("\033[".$left_right."C");
				}
			}
		}
		/**
		 * １行削除
		 */
		public static function line_clear(){
			print("\033[2K");
		}
		/**
		 * BackSpace
		 * @param integer $num
		 */
		public static function bs($num=0){
			if(empty($num)){
				print("\033[2K");
			}else{
				self::cur(0,$num*-1);
				print(str_repeat(' ',$num));
				self::cur(0,$num*-1);
			}
		}
		/**
		 * 改行つきで色付きでプリント
		 * @param string $msg
		 * @param string $color ANSI Colors
		 */
		public static function println($msg='',$color='0'){
			self::p($msg.PHP_EOL,$color);
		}
		/**
		 * White
		 * @param string $msg
		 */
		public static function println_white($msg){
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
							$target_list = explode(PHP_EOL,file_get_contents($target_list_db));
							$fp = fopen($tmp_db,'a');
		
							foreach(xdebug_get_code_coverage() as $file_path => $lines){
								if(false !== ($i = array_search($file_path,$target_list))){
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
		function eq($expectation,$result,$msg='failure equals'){
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
	if(!function_exists('b')){
		/**
		 * ブラウザを返す
		 * @return \testman\Browser
		 */
		function b(){
			return new \testman\Browser();
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
	foreach(array('coverage','output','coverage-dir') as $k){
		if(($v = \testman\Args::opt($k,null)) !== null && !is_bool($v)){
			\testman\Conf::set($k,$v);
		}
	}
	$version = '0.6.9';
	\testman\Std::println('testman '.$version.' (PHP '.phpversion().')'); // version
	\testman\Std::println();
	
	if(\testman\Args::opt('help')){
		\testman\Std::println_info('Usage: php '.basename(__FILE__).' [options] [dir/ | dir/file.php]');
		\testman\Std::println();
		\testman\Std::println_primary('Options:');
		\testman\Std::println('  --coverage <coverage file> Generate code coverage report in XML format');
		\testman\Std::println('  --coverd <coverage file> View coverage report');
		\testman\Std::println('  --output <file>   Log test execution in XML format to file');
		\testman\Std::println('  --list [keyword]  List test files');
		\testman\Std::println('  --info            Info setup[s]');
		\testman\Std::println('  --setup           View setup[s] script');
		\testman\Std::println('  --init            Create init files');
		\testman\Std::println();
	}else if(($keyword = \testman\Args::opt('list',false)) !== false){
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
	}else if(($p=\testman\Args::opt('info',false)) !== false){
		\testman\Finder::setup_info($p,false);
	}else if(($p=\testman\Args::opt('setup',false)) !== false){
		\testman\Finder::setup_info($p,true);
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
			\testman\Runner::start($testdir);
		}catch(\Exception $e){
			\testman\Std::println_danger(PHP_EOL.get_class($e).': '.$e->getMessage().PHP_EOL.PHP_EOL.$e->getTraceAsString());
		}
	}
}
