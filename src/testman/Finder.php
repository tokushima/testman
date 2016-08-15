<?php
namespace testman;

class Finder{
	/**
	 * テスト対象ファイルを探す
	 * @param string $test_dir
	 * @throws \InvalidArgumentException
	 * @return stirng[]
	 */
	public static function get_list($test_dir){
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
				$test_list[$name] = ['path'=>$test_path,'summary'=>$summary($src)];
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
	 */
	public static function setup_info($dir){
		$dir = realpath($dir);
		
		if($dir === false){
			throw new \InvalidArgumentException($dir.' not found');
		}
		list($var_types,$inc_list,$target_dir) = self::setup_teardown_files($dir, true);
			
		$summary_list = [];
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
		if(is_file($dir)){
			\testman\Std::println_warning('File:');
			\testman\Std::println('  '.\testman\Runner::short_name($dir));
			\testman\Std::println();
		}else{
			\testman\Std::println_warning('Dir:');
			\testman\Std::println('  '.\testman\Runner::short_name($target_dir));
			\testman\Std::println();
		}
		\testman\Std::println_warning('Summary:');
		\testman\Std::println('  '.implode(' > ',$summary_list));
		
		if(is_file($dir)){
			if(preg_match('/\/\*.+?\*\//s',file_get_contents($dir),$_m)){
				$desc = preg_replace("/^[\s]*\*[\s]{0,1}/m","",str_replace(['/'.'**','*'.'/'],'',$_m[0]));
			}
			\testman\Std::println_info('  '.str_repeat('-', 40));
			foreach(explode(PHP_EOL,trim($desc)) as $line){
				\testman\Std::println_info('   '.$line);
			}
			\testman\Std::println_info('  '.str_repeat('-', 40));
		}
		\testman\Std::println();
			
		ksort($var_types);
			
		\testman\Std::println_warning('Vars:');
		foreach($var_types as $name => $type){
			\testman\Std::println('  '.str_pad($type['type'],$tlen).' $'.str_pad($name,$nlen).' : '.$type['desc']);
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
					$inc_list[] = ['path'=>$f,'vars'=>$varnames,'summary'=>$summary];
				}
				$dir = dirname($dir);
			}
			if($is_setup){
				krsort($inc_list);
			}
			return [$var_types,$inc_list,$target_dir];
		}
		return [[],[],''];
	}
}
