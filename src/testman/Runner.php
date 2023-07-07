<?php
namespace testman;

class Runner{
	private static array $resultset = [];
	private static string $current_test;
	private static bool $start = false;
	private static array $vars = [];

	/**
	 * 現在実行しているテスト
	*/
	public static function current(): string{
		return self::$current_test;
	}

	private static function trim_msg(string $msg, int $len): string{
		if(strlen($msg) > $len){
			return mb_substr($msg,0,ceil($len/2)).' .. '.mb_substr($msg,ceil($len/2)*-1);
		}
		return $msg;
	}
	/**
	 * 対象のテスト群を実行する
	 */
	public static function start(string $testdir): array{
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
					self::trim_msg(\testman\Runner::short_name($test_path),80).' '
				);
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
						$file = str_replace(getcwd().DIRECTORY_SEPARATOR,'',$file);

						\testman\Std::println();
						\testman\Std::println_primary(' '.$testfile);
						
						if($testfile != $file){
							\testman\Std::println_white('  ('.$file.')');
						}
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
						$file = str_replace(getcwd().DIRECTORY_SEPARATOR,'',$file);
						
						$msgarr = explode(PHP_EOL,$msg);
						$summary = array_shift($msgarr);
							
						\testman\Std::println();
						\testman\Std::println_primary(' '.$testfile);

						if($testfile != $file){
							\testman\Std::println_white('  ('.$file.')');
						}
						\testman\Std::println_danger('  ['.$line.']: '.$summary);
						\testman\Std::println($tab.implode(PHP_EOL.$tab,$msgarr));
						break;
				}
			}
			\testman\Std::println(str_repeat('=',80));
			\testman\Std::println_info(sprintf('success %d, failures %d, errors %d (%.05f sec / %s MByte)',$success,$fail,$exception,$exe_time,$use_memory));
			\testman\Std::println();
		}catch(\Exception $e){
			\testman\Std::println_danger(PHP_EOL.PHP_EOL.'Failure:'.PHP_EOL.PHP_EOL.$e->getMessage().PHP_EOL.$e->getTraceAsString());
		}
		return self::$resultset;
	}
	
	private static function vars_type_validation(string $type, string $name, $var): void{
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
				case 'int':
					if(!is_int($v)){
						throw new \testman\DefinedVarsInvalidTypeException($name.' must be an '.$type);
					}
					break;
				case 'float':
					if(!is_float($v)){
						throw new \testman\DefinedVarsInvalidTypeException($name.' must be an '.$type);
					}
					break;
				case 'bool':					
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
	private static function exec_include(bool $_is_setup, array $_inc, array $_var_types): void{
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
	private static function exec_setup_teardown(string $test_file, bool $is_setup): void{
		[$var_types, $inc_list, $target_dir] = \testman\Finder::setup_teardown_files($test_file,$is_setup);
			
		foreach($inc_list as $inc){
			self::exec_include($is_setup,$inc,$var_types);
		}
	}
	public static function short_name(string $test_file): string{
		return str_replace(getcwd().DIRECTORY_SEPARATOR,'',$test_file);
	}
	private static function exec(string $test_file): array{
		self::$vars = [];
		self::$current_test = $test_file;
		$res = null;

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
		}catch(\testman\DefinedVarsRequireException $e){
			list($debug) = $e->getTrace();
			if(!isset($res) && $debug['file'] === __FILE__ && isset($debug['args'][1]['path'])){
				$res = [-2, 0, $debug['args'][1]['path'], 0, ((string)$e)];				
			}
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
				
		self::$resultset[$test_name] = $res;
		return [$test_name,$res];
	}
}