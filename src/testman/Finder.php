<?php
namespace testman;

class Finder{
	/**
	 * テスト対象ファイルを探す
	 */
	public static function get_list(string $test_dir): array{
		$test_list = [];

		if(is_dir($test_dir)){
			foreach(new \RegexIterator(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($test_dir,
					\FilesystemIterator::CURRENT_AS_FILEINFO|\FilesystemIterator::SKIP_DOTS|\FilesystemIterator::UNIX_PATHS
			),\RecursiveIteratorIterator::SELF_FIRST),'/\.php$/') as $f){
				if(!preg_match('/\/[\._]/',$f->getPathname()) && strpos($f->getPathname(),\testman\Conf::settings_path('testman.')) === false){
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
	 */
	public static function summary_list(string $testdir, $keyword=null): array{
		$cwd = getcwd().DIRECTORY_SEPARATOR;

		$summary = function($src){
			$summary = '';
				
			if(preg_match('/\/\*.+?\*\//s',$src,$m)){
				[$summary] = explode(PHP_EOL,trim(
					preg_replace('/@.+/','',
						preg_replace("/^[\s]*\*[\s]{0,1}/m","",str_replace(['/'.'**','*'.'/'],'',$m[0]))
					)
				));
			}
			return $summary;
		};
		$len = 8;
		$test_list = [];
		foreach(self::get_list($testdir) as $test_path){
			$src = file_get_contents($test_path);

			if($keyword === true || strpos($test_path,$keyword) !== false || strpos($src,$keyword) !== false){
				$name = str_replace($cwd,'',$test_path);

				if($len < strlen($name)){
					$len = strlen($name);
				}
				$test_list[$name] = ['path'=>$test_path, 'summary'=>$summary($src)];
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
	 */
	public static function setup_info(string $dir, bool $verbose): void{
		$dir = realpath($dir);
		
		if($dir === false){
			throw new \InvalidArgumentException($dir.' not found');
		}
		[$var_types, $inc_list, $target_dir]= self::setup_teardown_files($dir, true);
		
		$leading_space = '  ';
		$func_mb_str_pad = function(string $input, int $pad_length): string {
			return $input.str_repeat(' ', max(0, $pad_length - mb_strwidth($input, 'UTF-8')));
		};

		if(is_file($dir)){
			\testman\Std::println_warning('File:');
			\testman\Std::println($leading_space.\testman\Runner::short_name($dir));
			\testman\Std::println();
		}else{
			\testman\Std::println_warning('Dir:');
			\testman\Std::println($leading_space.\testman\Runner::short_name($target_dir));
			\testman\Std::println();
		}


		\testman\Std::println_warning('Summary:');
		if(is_file($dir)){
			$desc = '';
			if(preg_match('/\/\*.+?\*\//s',file_get_contents($dir),$_m)){
				$desc = preg_replace("/^[\s]*\*[\s]{0,1}/m","",str_replace(['/'.'**','*'.'/'],'',$_m[0]));
			}
			foreach(explode(PHP_EOL,trim($desc)) as $line){
				\testman\Std::println($leading_space.$line);
			}
		}
		\testman\Std::println();


		\testman\Std::println_warning('Preprocessing:');
		$max_width = 0;
		foreach($inc_list as $inc){
			$max_width = max($max_width, mb_strwidth($inc['summary'] ?? ''));
		}

		$count = 1;
		foreach($inc_list as $inc){
			$summary = str_pad($count++, 2, ' ', STR_PAD_LEFT).'. '.(empty($inc['summary']) ? '[NONE]' : $inc['summary']);

			if($verbose){
				$summary = $func_mb_str_pad($summary, $max_width + 10, '　', STR_PAD_RIGHT).'   '.\testman\Runner::short_name($inc['path']);
			}
			\testman\Std::println($leading_space.$summary);
		}
		\testman\Std::println();
		

		\testman\Std::println_warning('Vars:');
		$nlen = $tlen = $dwidth = 0;
		foreach($var_types as $name => $var) {
			$nlen = max($nlen, strlen($name));
			$tlen = max($tlen, strlen($var['type']));
		}
		$var_summarys = [];
		foreach($var_types as $name => $var){
			$var_summarys[$name] = str_pad($var['type'],$tlen).' $'.str_pad($name,$nlen).' : '.$var['desc'];
			$dwidth = max($dwidth, mb_strwidth($var_summarys[$name] ?? ''));
		}
		foreach($var_types as $name => $var) {
			if($verbose){
				$vars_summary = $func_mb_str_pad($var_summarys[$name], $dwidth + 10, '　', STR_PAD_RIGHT).'   '.\testman\Runner::short_name($var['path']).($var['rewrite'] ? ' (*)' : '');
			}else{
				$vars_summary = $var_summarys[$name];
			}
			\testman\Std::println($leading_space.$vars_summary);
		}
		\testman\Std::println();
	}
	/**
	 * setup/teardownを探す
	 */
	public static function setup_teardown_files(string $testdir, bool $is_setup): array{
		if(is_dir($dir=$testdir) || is_dir($dir=dirname($testdir))){
			$file = ($is_setup) ? '__setup__.php' : '__teardown__.php';
			$inc_list = [];
			$var_types = [];
			$target_dir = $dir;

			while(strlen($dir) >= strlen(getcwd())){
				if(is_file($f=($dir.'/'.$file))){
					$varnames = [];
					$summary = null;

					if($is_setup && preg_match('/\/\*.+?\*\//s',file_get_contents($f),$_m)){
						$desc = preg_replace("/^[\s]*\*[\s]{0,1}/m","",str_replace(['/'.'**','*'.'/'],'',$_m[0]));
							
						if(preg_match_all('/@.+/',$desc,$_as)){
							foreach($_as[0] as $_m){
								if(preg_match("/@var\s+([^\s]+)\s+\\$(\w+)(.*)/",$_m,$_p)){
									$var_types[$_p[2]]['rewrite'] = array_key_exists($_p[2], $var_types);
									$var_types[$_p[2]]['path'] = $f;
									$var_types[$_p[2]]['type'] = $_p[1];

									if(!isset($var_types[$_p[2]]['desc']) || empty($var_types[$_p[2]]['desc'])){
										$var_types[$_p[2]]['desc'] = trim($_p[3]);
									}
									$varnames[] = $_p[2];
								}else if(preg_match("/@var\s+\\$(\w+)(.*)/",$_m,$_p)){
									$var_types[$_p[1]]['rewrite'] = array_key_exists($_p[1], $var_types);
									$var_types[$_p[1]]['path'] = $f;
									$var_types[$_p[1]]['type'] = 'string';

									if(!isset($var_types[$_p[1]]['desc']) || empty($var_types[$_p[1]]['desc'])){
										$var_types[$_p[1]]['desc'] = trim($_p[2]);
									}
									$varnames[] = $_p[1];
								}
							}
						}
						[$summary] = explode(PHP_EOL,trim(preg_replace('/@.+/','',$desc)));
					}
					$inc_list[] = ['path'=>$f, 'vars'=>$varnames, 'summary'=>$summary];
				}
				$dir = dirname($dir);
			}
			if($is_setup){
				krsort($inc_list);
			}
			ksort($var_types);

			return [$var_types, $inc_list, $target_dir];
		}
		return [[],[],''];
	}
}
