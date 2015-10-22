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
