<?php
namespace testman;

class Runner{
	private static $resultset = [];
	private static $current_test;
	private static $start = false;
	private static $vars = [];
	private static $benchmark = [];

	/**
	 * 現在実行しているテスト
	*/
	public static function current(){
		return self::$current_test;
	}
	public static function benchmark(){
		return self::$benchmark;
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
			ini_set('display_errors','On');
			ini_set('html_errors','Off');
			ini_set('error_reporting',E_ALL);
			ini_set('xdebug.overload_var_dump',0);
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

			for($i=0;$i<5;$i++){
				\testman\Std::println();
			}
			\testman\Std::cur(-5,0);
						
			$is_head_print = false;
			if(null !== ($f = \testman\Conf::has_settings('settings.php'))){
				$msg = 'Settings: '.$f;
				\testman\Std::println($msg,'36');
					include_once($f);
				$is_head_print = true;
			}
			
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
				
			$coverage = \testman\Conf::get('coverage');
				
			if(isset($coverage)){
				if(empty($coverage)){
					$coverage = 'coverage.xml';
				}
				$coverage_taget_dir = \testman\Conf::get('coverage-dir',getcwd().'/lib');
				$coverage_taget_dir_real = realpath($coverage_taget_dir);
			
				if($coverage_taget_dir_real === false){
					throw new \testman\NotFoundException('Coverage target not found: '.$coverage_taget_dir);
				}
				if(\testman\Coverage::start($coverage,$coverage_taget_dir)){
					$msg = 'Coverage: '.$coverage_taget_dir_real;
					\testman\Std::println($msg,'36');
					$is_head_print = true;
				}
			}
			\testman\Benchmark::init();
			
			$testdir = realpath($testdir);
			$success = $fail = $exception = $exe_time = $use_memory = 0;
			
			$msg = 'Finding '.$testdir;
			\testman\Std::p($msg,'36');
			$test_list = \testman\Finder::get_list($testdir);
			\testman\Std::bs(strlen($msg));

			if(null !== ($f = \testman\Conf::has_settings('fixture.php'))){
				$msg = 'Fixture: '.$f;
				\testman\Std::println($msg,'36');
					include_once($f);
				$is_head_print = true;
			}
			
			if($is_head_print){
				\testman\Std::println();
			}
			
			$start_time = microtime(true);
			$start_mem = round(number_format((memory_get_usage() / 1024 / 1024),3),4);
			$ey = $cnt = 0;
			$testcnt = sizeof($test_list);
			
			foreach($test_list as $test_path){
				$cnt++;

				$msg = 'Running.. ('.($cnt.'/'.$testcnt).') '.(
					(
						(\testman\Conf::get('stdbs',true) === false) ?
							\testman\Runner::short_name($test_path) :
							self::trim_msg(\testman\Runner::short_name($test_path),80)
					).' '
				);
				\testman\Std::p($msg,33);
				list($test_name,$res) = \testman\Runner::exec($test_path);
				\testman\Std::bs(strlen($msg));
				
				if(\testman\Conf::get('stdbs',true) === true){
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

			\testman\Std::println_info(PHP_EOL.'Results:');

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
			
			if(!empty(\testman\Benchmark::is_running())){
				\testman\Benchmark::write();
				\testman\Std::println_primary(' Written Benchmark: '.\testman\Benchmark::save_path());
			}
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

		$list = [];
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
	private static function vars_type_validation($type,$name,$var){
		$is_a = false;
		if(substr($type,-2) == '[]'){
			$is_a = true;
			$type = substr($type,0,-2);
		}
		if($is_a && !is_array($var)){
			throw new \testman\DefinedVarsInvalidTypeException('expects '.$name.' to be array');
		}
		foreach((($is_a) ? $var : [$var]) as $v){
			switch($type){
				case 'string':
					if(!is_string($v)){
						throw new \testman\DefinedVarsInvalidTypeException($name.' must be an '.$type);
					}
					break;
				case 'integer':
					if(!is_int($v)){
						throw new \testman\DefinedVarsInvalidTypeException($name.' must be an '.$type);
					}
					break;
				case 'float':
					if(!is_float($v)){
						throw new \testman\DefinedVarsInvalidTypeException($name.' must be an '.$type);
					}
					break;
				case 'boolean':
					if(!is_bool($v)){
						throw new \testman\DefinedVarsInvalidTypeException($name.' must be an '.$type);
					}
					break;
				default:
					if(!is_object($v)){
						throw new \testman\DefinedVarsInvalidTypeException($name.' must be an '.$type);
					}
					if(!($v instanceof $type)){
						throw new \testman\DefinedVarsInvalidTypeException($name.'('.get_class($v).') must be an '.$type);
					}
			}
		}
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
				self::vars_type_validation($type,$k,$_getvars[$k]);
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
		self::$vars = [];
		self::$current_test = $test_file;

		\testman\Benchmark::start();
	
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
			$res = [1,(round(microtime(true) - $test_exec_start_time,3))];
		}catch(\testman\AssertFailure $e){
			list($debug) = $e->getTrace();
			$res = [-1,0,$debug['file'],$debug['line'],$e->getMessage(),$e->expectation(),$e->result(),$e->has()];
			ob_end_clean();
		}catch(\Exception $e){
			$trace = $e->getTrace();
			$root = preg_replace('/^phar:\/\/(.+\.phar)\/src\/testman\/.+$/','\\1',__FILE__);
			
			for($i=sizeof($trace);$i>=0;$i--){
				if(isset($trace[$i]['file']) && strpos($trace[$i]['file'],$root) === false){
					$res = [-2,0,$trace[$i]['file'],$trace[$i]['line'],((string)$e).PHP_EOL.$trace[$i]['file'].PHP_EOL.$root];
					break;
				}
			}
			if(!isset($res)){
				$res = [-2,0,$e->getFile(),$e->getLine(),((string)$e)];
			}
			ob_end_clean();
		}
		$test_name = self::short_name($test_file);
		self::exec_setup_teardown($test_file,false);
		
		\testman\Benchmark::stop($test_name);
		
		self::$resultset[$test_name] = $res;
		return [$test_name,$res];
	}
}