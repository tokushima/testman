<?php
namespace testman;

class Conf{
	static private array $conf = [];

	public static function set(array $conf): void{
		foreach($conf as $k => $v){
			self::$conf[$k] = $v;
		}
	}
	public static function get(string $name, $default=null){
		if(isset(self::$conf[$name])){
			return self::$conf[$name];
		}
		return $default;
	}
	public static function has_settings(string $name): ?string{
		try{
			$path = self::settings_path('testman.'.$name);
			return (is_file($path) || is_dir($path) ? $path : null);
		}catch(\InvalidArgumentException $e){
		}
		return null;
	}
	public static function settings_path(string $name): string{
		$d = debug_backtrace(false);
		$d = array_pop($d);
		$dir = str_replace('phar://','',dirname($d['file']));
		
		if(!is_dir($dir)){
			throw new \InvalidArgumentException('not found '.$dir);
		}
		return $dir.'/'.$name;
	}

	public static function log_debug_callback(string $message): void{
		$log_debug_callback = self::get('log_debug_callback');
		if(is_callable($log_debug_callback)){
			call_user_func_array($log_debug_callback, [$message]);
		}
	}
}