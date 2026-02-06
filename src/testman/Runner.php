<?php
namespace testman;

class Runner{
	private const LINE_WIDTH = 80;
	private const TAB = '   ';
	private const PROGRESS_WIDTH = 30;

	private const ANSI_RED = '31';
	private const ANSI_GREEN = '32';
	private const ANSI_YELLOW = '33';
	private const ANSI_CYAN = '36';
	private const ANSI_WHITE = '37';
	private const ANSI_GRAY = '90';
	private const ANSI_BOLD = '1';

	private static array $resultset = [];
	private static string $current_test;
	private static bool $start = false;
	private static array $vars = [];
	private static bool $interrupted = false;

	/**
	 * 現在実行しているテスト
	 */
	public static function current(): string{
		return self::$current_test;
	}

	private static function trim_msg(string $msg, int $len = self::LINE_WIDTH): string{
		if(strlen($msg) > $len){
			$half = (int)ceil($len / 2);
			return mb_substr($msg, 0, $half).' .. '.mb_substr($msg, $half * -1);
		}
		return $msg;
	}
	private static bool $echo_disabled = false;

	/**
	 * ターミナルのエコーを無効化
	 */
	private static function disable_terminal_echo(): void{
		if(function_exists('posix_isatty') && posix_isatty(STDIN)){
			@exec('stty -echo 2>/dev/null');
			self::$echo_disabled = true;
		}
	}

	/**
	 * ターミナルのエコーを復元
	 */
	private static function restore_terminal_echo(): void{
		if(self::$echo_disabled){
			@exec('stty echo 2>/dev/null');
			self::$echo_disabled = false;
		}
	}

	/**
	 * シグナルハンドラを登録
	 */
	private static function register_signal_handlers(): void{
		if(!function_exists('pcntl_signal')){
			return;
		}

		pcntl_async_signals(true);

		$handler = function($signo){
			self::$interrupted = true;
			self::restore_terminal_echo();
			\testman\Std::println();
			\testman\Std::println_warning('Interrupted (signal '.$signo.'), finishing current test...');
		};

		pcntl_signal(SIGINT, $handler);
		pcntl_signal(SIGTERM, $handler);
	}

	/**
	 * 中断されたかどうか
	 */
	public static function is_interrupted(): bool{
		return self::$interrupted;
	}

	/**
	 * 対象のテスト群を実行する
	 * @param string $testdir テストディレクトリ
	 * @param int|bool $parallel 並列数（false=逐次, true=自動, int=指定数）
	 */
	public static function start(string $testdir, $parallel = false): array{
		if(self::$start){
			return self::$resultset;
		}
		if(!is_dir($testdir) && !is_file($testdir)){
			throw new \InvalidArgumentException($testdir.' not found');
		}
		self::$start = true;
		self::$interrupted = false;

		// シグナルハンドラを登録
		self::register_signal_handlers();

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
			// opcache_resetは速度低下の原因になるため削除
			clearstatcache(true);

			set_error_handler(function($n,$s,$f,$l){
				throw new \ErrorException($s,0,$n,$f,$l);
			});

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
			$start_mem = round(number_format((memory_get_usage() / 1024 / 1024), 3), 4);
			$testcnt = sizeof($test_list);

			// ターミナルのエコーを無効化（キー入力表示を防ぐ）
			self::disable_terminal_echo();

			// 並列実行
			if($parallel !== false && $testcnt > 1 && function_exists('pcntl_fork')){
				$workers = ($parallel === true) ? self::get_cpu_cores() : max(1, (int)$parallel);
				$workers = min($workers, $testcnt);

				\testman\Std::println_info(sprintf('Running %d tests with %d workers...', $testcnt, $workers));
				\testman\Std::println();

				self::$resultset = self::run_parallel($test_list, $workers);
			}else{
				// 逐次実行
				self::run_sequential($test_list);
			}

			$exe_time = round((microtime(true) - (float)$start_time), 4);
			$use_memory = round(number_format((memory_get_usage() / 1024 / 1024), 3), 4) - $start_mem;

			// 結果集計と表示
			[$success, $fail, $exception] = self::print_results();

			// サマリ表示
			self::print_summary($success, $fail, $exception, $start_time, $exe_time, $use_memory, self::$interrupted, $testcnt);
			\testman\Std::println();

			// ターミナルのエコーを復元
			self::restore_terminal_echo();
		}catch(\Exception $e){
			self::restore_terminal_echo();
			\testman\Std::println_danger(PHP_EOL.PHP_EOL.'Failure:'.PHP_EOL.PHP_EOL.$e->getMessage().PHP_EOL.$e->getTraceAsString());
		}
		return self::$resultset;
	}

	/**
	 * CPUコア数を取得
	 */
	private static function get_cpu_cores(): int{
		if(is_file('/proc/cpuinfo')){
			$cpuinfo = file_get_contents('/proc/cpuinfo');
			preg_match_all('/^processor/m', $cpuinfo, $matches);
			return count($matches[0]) ?: 4;
		}
		// macOS
		$cores = (int)shell_exec('sysctl -n hw.ncpu 2>/dev/null');
		return $cores > 0 ? $cores : 4;
	}

	/**
	 * 逐次実行
	 */
	private static function run_sequential(array $test_list): void{
		$testcnt = sizeof($test_list);
		$ey = $cnt = 0;
		$pass_cnt = $fail_cnt = 0;

		for($i = 0; $i < 5; $i++){
			\testman\Std::println();
		}
		\testman\Std::cur(-5, 0);

		foreach($test_list as $test_path){
			// 中断チェック
			if(self::$interrupted){
				break;
			}

			$cnt++;
			$test_short = self::short_name($test_path);

			// プログレス表示
			$progress_line = self::render_progress($cnt, $testcnt, $pass_cnt, $fail_cnt, $test_short);
			\testman\Std::p($progress_line);

			[$test_name, $res] = self::exec($test_path);
			\testman\Std::bs(mb_strlen($progress_line));

			if($res[0] == 1){
				$pass_cnt++;
			}else{
				$fail_cnt++;
				if($ey == 0){
					$ey = 2;
					\testman\Std::cur($ey, 0);
					\testman\Std::p('Failures:', self::ANSI_RED);
					\testman\Std::cur($ey * -1, -9);
				}
				$ey++;

				$fail_msg = '  '.self::trim_msg($test_name, 70).':'.$res[3];
				\testman\Std::cur($ey, 0);
				\testman\Std::p($fail_msg.PHP_EOL.PHP_EOL.PHP_EOL, self::ANSI_RED);
				\testman\Std::cur(($ey + 3) * -1, strlen($fail_msg) * -1);
			}
			self::$resultset[$test_name] = $res;
		}

		// 実行中の表示をクリア
		\testman\Std::line_clear();
		if($ey > 0){
			for($a = 0; $a <= $ey; $a++){
				\testman\Std::cur(1, 0);
				\testman\Std::line_clear();
			}
			\testman\Std::cur($ey * -1, 0);
		}
	}

	/**
	 * 並列実行
	 */
	private static function run_parallel(array $test_list, int $workers): array{
		$results = [];
		$running = [];
		$testcnt = count($test_list);
		$completed = 0;
		$pass_cnt = $fail_cnt = 0;

		// テストをキューに入れる
		$queue = $test_list;

		while(!empty($queue) || !empty($running)){
			// 中断チェック - 新規ワーカーは起動しない
			if(self::$interrupted){
				$queue = []; // キューをクリア
			}

			// ワーカーを起動
			while(count($running) < $workers && !empty($queue) && !self::$interrupted){
				$test_path = array_shift($queue);
				$test_name = self::short_name($test_path);

				// 一時ファイルで結果を受け取る
				$tmp_file = sys_get_temp_dir().'/testman_'.md5($test_path).'.json';

				// サブプロセスでテストを実行
				$cmd = sprintf(
					'php -r %s %s %s 2>&1',
					escapeshellarg(self::get_worker_code()),
					escapeshellarg($test_path),
					escapeshellarg($tmp_file)
				);

				$proc = proc_open($cmd, [
					0 => ['pipe', 'r'],
					1 => ['pipe', 'w'],
					2 => ['pipe', 'w']
				], $pipes);

				if(is_resource($proc)){
					stream_set_blocking($pipes[1], false);
					stream_set_blocking($pipes[2], false);
					$running[] = [
						'proc' => $proc,
						'pipes' => $pipes,
						'test_path' => $test_path,
						'test_name' => $test_name,
						'tmp_file' => $tmp_file,
						'start' => microtime(true)
					];
				}
			}

			// 完了したプロセスをチェック
			foreach($running as $key => $job){
				$status = proc_get_status($job['proc']);

				if(!$status['running']){
					// 出力をキャプチャ
					$stdout = stream_get_contents($job['pipes'][1]);
					$stderr = stream_get_contents($job['pipes'][2]);

					fclose($job['pipes'][0]);
					fclose($job['pipes'][1]);
					fclose($job['pipes'][2]);
					$exit_code = proc_close($job['proc']);

					$completed++;

					// 結果を読み取る
					if(is_file($job['tmp_file'])){
						$result = json_decode(file_get_contents($job['tmp_file']), true);
						@unlink($job['tmp_file']);

						if($result){
							$results[$job['test_name']] = $result;
							if($result[0] == 1){
								$pass_cnt++;
							}else{
								$fail_cnt++;
							}
						}else{
							$error_msg = 'Failed to parse result';
							if(!empty($stderr)) $error_msg .= ': '.$stderr;
							$results[$job['test_name']] = [-2, 0, $job['test_path'], 0, $error_msg];
							$fail_cnt++;
						}
					}else{
						$error_msg = 'Result file not found (exit='.$exit_code.')';
						if(!empty($stderr)) $error_msg .= ': '.substr($stderr, 0, 200);
						if(!empty($stdout)) $error_msg .= ' stdout: '.substr($stdout, 0, 200);
						$results[$job['test_name']] = [-2, 0, $job['test_path'], 0, $error_msg];
						$fail_cnt++;
					}

					// プログレス表示
					$progress = sprintf(
						"\r[%d/%d] %s %d%% \033[%sm%d ✓\033[0m \033[%sm%d ✗\033[0m",
						$completed, $testcnt,
						str_repeat('█', (int)(($completed / $testcnt) * self::PROGRESS_WIDTH)).
						str_repeat('░', self::PROGRESS_WIDTH - (int)(($completed / $testcnt) * self::PROGRESS_WIDTH)),
						(int)(($completed / $testcnt) * 100),
						self::ANSI_GREEN, $pass_cnt,
						self::ANSI_RED, $fail_cnt
					);
					\testman\Std::p($progress);

					unset($running[$key]);
				}
			}

			// CPU負荷軽減
			if(!empty($running)){
				usleep(10000); // 10ms
			}
		}

		\testman\Std::println();
		return $results;
	}

	/**
	 * ワーカー用のPHPコード
	 */
	private static function get_worker_code(): string{
		$phar = \Phar::running(false);
		if(empty($phar)){
			$phar = __DIR__.'/../../main.php';
		}
		$cwd = \testman\Finder::cwd();

		// 親プロセスでパスを解決しておく
		$settings_path = \testman\Conf::has_settings('settings.php');
		$lib_dir = \testman\Conf::has_settings('lib');
		if($lib_dir !== null){
			$lib_dir = realpath($lib_dir);
		}
		$resources_dir = \testman\Conf::has_settings('resources');
		if($resources_dir !== null){
			$resources_dir = realpath($resources_dir);
		}

		return '
			error_reporting(E_ALL);
			ini_set("display_errors", "Off");

			$test_path = $argv[1] ?? "";
			$tmp_file = $argv[2] ?? "";

			if(empty($test_path) || empty($tmp_file)){
				exit(1);
			}

			// カレントディレクトリを親プロセスと同じにする
			chdir('.var_export($cwd, true).');

			// bootstrap/autoload読み込み
			$bootstrap_files = ['.var_export($cwd, true).'."/bootstrap.php", '.var_export($cwd, true).'."/vendor/autoload.php"];
			foreach($bootstrap_files as $f){
				if(is_file($f)){
					ob_start();
					include_once($f);
					ob_end_clean();
					break;
				}
			}

			try{
				require_once('.var_export($phar, true).');

				// settings.php読み込み（fixture.phpは親で1回のみ実行済み）
				$settings_path = '.var_export($settings_path, true).';
				if($settings_path !== null){
					ob_start();
					include_once($settings_path);
					ob_end_clean();
				}

				// testman.resources パス設定
				$resources_dir = '.var_export($resources_dir, true).';
				if($resources_dir !== null){
					\testman\Conf::set(["resources_dir" => $resources_dir]);
				}

				// testman.lib オートローダー登録
				$lib_dir = '.var_export($lib_dir, true).';
				if($lib_dir !== null){
					$bs = chr(92); // backslash
					spl_autoload_register(function($class) use ($lib_dir, $bs){
						$cp = str_replace($bs,"/",(substr($class,0,1) == $bs ? substr($class,1) : $class));
						if(strpos($cp,"test/") === 0 && is_file($f=($lib_dir."/".substr($cp,5).".php"))){
							require_once($f);
							if(class_exists($class,false) || interface_exists($class,false) || trait_exists($class,false)){
								return true;
							}
						}
						return false;
					},true,false);
				}

				ob_start();
				\testman\Runner::exec_single($test_path);
				ob_end_clean();
			}catch(\Throwable $e){
				@file_put_contents($tmp_file, json_encode([-2, 0, $test_path, $e->getLine(), (string)$e]));
				exit(1);
			}
		';
	}

	/**
	 * 単一テストを実行（並列用）
	 */
	public static function exec_single(string $test_path): void{
		$tmp_file = $GLOBALS['argv'][2] ?? '';

		self::$vars = [];
		self::$current_test = $test_path;

		try{
			ob_start();
			self::exec_setup_teardown($test_path, true);
			$test_exec_start_time = microtime(true);

			foreach(self::$vars as $k => $v){
				$$k = $v;
			}
			include($test_path);
			ob_get_clean();

			$res = [1, round(microtime(true) - $test_exec_start_time, 3)];
		}catch(\testman\AssertFailure $e){
			[$debug] = $e->getTrace();
			$res = [-1, 0, $debug['file'], $debug['line'], $e->getMessage(), $e->expectation(), $e->result(), $e->has()];
			ob_end_clean();
		}catch(\Exception $e){
			$res = [-2, 0, $e->getFile(), $e->getLine(), (string)$e];
			ob_end_clean();
		}

		self::exec_setup_teardown($test_path, false);
		file_put_contents($tmp_file, json_encode($res));
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
		return str_replace(\testman\Finder::cwd().DIRECTORY_SEPARATOR,'',$test_file);
	}

	/**
	 * 最後のディレクトリ/ファイル名のみを取得（プログレス表示用）
	 */
	private static function last_dir_filename(string $path): string{
		$parts = explode('/', $path);
		$count = count($parts);
		if($count >= 2){
			return $parts[$count - 2].'/'.$parts[$count - 1];
		}
		return $path;
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
		return [$test_name, $res];
	}

	/**
	 * 結果を表示して集計を返す
	 */
	private static function print_results(): array{
		$success = $fail = $exception = 0;
		$cwd = \testman\Finder::cwd().DIRECTORY_SEPARATOR;

		\testman\Std::println_info(PHP_EOL.'Results:');

		foreach(self::$resultset as $testfile => $info){
			switch($info[0]){
				case 1:
					$success++;
					break;
				case -1:
					$fail++;
					self::print_failure($testfile, $info, $cwd);
					break;
				case -2:
					$exception++;
					self::print_exception($testfile, $info, $cwd);
					break;
			}
		}
		return [$success, $fail, $exception];
	}

	/**
	 * アサーション失敗の表示
	 */
	private static function print_failure(string $testfile, array $info, string $cwd): void{
		[, $time, $file, $line, $msg, $r1, $r2, $has] = $info;
		$file = str_replace($cwd, '', $file);

		\testman\Std::println();
		\testman\Std::println_primary(' '.$testfile);

		if($testfile != $file){
			\testman\Std::println_white('  ('.$file.')');
		}
		\testman\Std::println_danger('  ['.$line.']: '.$msg);

		if($has){
			self::print_diff('expect', $r1);
			self::print_diff('result', $r2);
		}
	}

	/**
	 * 例外エラーの表示
	 */
	private static function print_exception(string $testfile, array $info, string $cwd): void{
		[, $time, $file, $line, $msg] = $info;
		$file = str_replace($cwd, '', $file);

		$msgarr = explode(PHP_EOL, $msg);
		$summary = array_shift($msgarr);

		\testman\Std::println();
		\testman\Std::println_primary(' '.$testfile);

		if($testfile != $file){
			\testman\Std::println_white('  ('.$file.')');
		}
		\testman\Std::println_danger('  ['.$line.']: '.$summary);
		\testman\Std::println(self::TAB.implode(PHP_EOL.self::TAB, $msgarr));
	}

	/**
	 * 期待値/結果の差分表示
	 */
	private static function print_diff(string $label, $value): void{
		$label = ' '.$label.' ';
		$line_len = self::LINE_WIDTH - strlen(self::TAB) - 3;
		$dashes = str_repeat('-', $line_len - strlen($label));

		\testman\Std::println_white(self::TAB.$dashes.$label.'---');

		ob_start();
		var_dump($value);
		$dump = ob_get_clean();

		\testman\Std::println(self::TAB.str_replace(PHP_EOL, PHP_EOL.self::TAB, $dump));
	}

	/**
	 * サマリ表示
	 */
	private static function print_summary(int $success, int $fail, int $exception, float $start_time, float $exe_time, float $use_memory, bool $interrupted = false, int $total_planned = 0): void{
		\testman\Std::println(str_repeat('=', self::LINE_WIDTH));

		$total = $success + $fail + $exception;
		$status_color = ($fail + $exception > 0) ? self::ANSI_RED : self::ANSI_GREEN;

		if($interrupted){
			$status_color = self::ANSI_YELLOW;
			$summary = sprintf(
				'INTERRUPTED: %d/%d tests run: %d passed, %d failed, %d errors',
				$total, $total_planned, $success, $fail, $exception
			);
		}else{
			$summary = sprintf(
				'%d tests: %d passed, %d failed, %d errors',
				$total, $success, $fail, $exception
			);
		}
		$timing = sprintf(
			'(%s / %.03fs / %.02fMB)',
			date('Y-m-d H:i:s', (int)$start_time),
			$exe_time,
			$use_memory
		);

		\testman\Std::println($summary.' '.$timing, $status_color);
	}

	/**
	 * プログレスバーを生成
	 */
	private static function render_progress(int $current, int $total, int $pass, int $fail, string $filename): string{
		$percent = ($total > 0) ? (int)(($current / $total) * 100) : 0;
		$filled = (int)(($current / $total) * self::PROGRESS_WIDTH);
		$empty = self::PROGRESS_WIDTH - $filled;

		// プログレスバー: ████░░░░
		$bar_filled = str_repeat('█', $filled);
		$bar_empty = str_repeat('░', $empty);

		// 色付きプログレスバー
		$bar = "\033[".self::ANSI_GREEN."m".$bar_filled."\033[".self::ANSI_GRAY."m".$bar_empty."\033[0m";

		// カウンター
		$counter = sprintf('[%d/%d]', $current, $total);

		// ステータス (pass/fail)
		$status = "\033[".self::ANSI_GREEN."m".$pass." ✓\033[0m";
		if($fail > 0){
			$status .= " \033[".self::ANSI_RED."m".$fail." ✗\033[0m";
		}

		// ファイル名（最後のディレクトリ/ファイル名のみ）
		$short_filename = self::last_dir_filename($filename);

		return sprintf('%s %s %3d%% %s %s', $counter, $bar, $percent, $status, $short_filename);
	}
}