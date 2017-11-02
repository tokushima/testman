<?php
namespace testman;

class Benchmark{
	private static $path;
	private static $report_base = [];
	private static $report = [];
	
	public static function init(){
		$save_path = null;
		
		if(\testman\Args::has_opt('benchmark')){
			$save_path = \testman\Args::opt('benchmark');
		}
		if(empty($save_path)){
			$save_path = \testman\Conf::get('benchmark');
		}
		
		if(!empty($save_path)){
			if(!is_dir(dirname($save_path))){
				if(!mkdir(dirname($save_path))){
					throw new \InvalidArgumentException('Creation of benchmark file failed');
				}
			}
			if(is_file($save_path)){
				unlink($save_path);
			}
			if(!file_put_contents($save_path,sprintf("%s\t%s\t%s\t%s\t%s".PHP_EOL,'Path','Time','Mem','Peak Mem','Req'))){
				throw new \InvalidArgumentException('Creation of benchmark file failed');
			}
		}
		self::$path = $save_path;
		
		return self::$path;
	}
	
	public static function is_running(){
		return !empty(self::$path);
	}
	
	public static function save_path(){
		return self::$path;
	}
	
	public static function start(){
		self::$report_base = [
			'm'=>memory_get_usage(),
			't'=>microtime(true),
		];
	}
	public static function stop($test_name){
		$memory_get_usage = memory_get_usage();
		
		if(0 > ($memory_get_usage - self::$report_base['m'])){
			$memory_get_usage = self::$report_base['m'];
		}
		self::$report[$test_name] = [
			$test_name,
			round((microtime(true) - (float)self::$report_base['t']),4),
			ceil($memory_get_usage),
			ceil(memory_get_peak_usage()),
			1,
		];
	}
	public static function write(){
		if(!empty(self::save_path())){
			foreach(self::$report as $name => $values){
				file_put_contents(self::save_path(),implode("\t",$values).PHP_EOL,FILE_APPEND);
			}
		}
	}
}
