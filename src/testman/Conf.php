<?php
namespace testman;

class Conf{
	static private array $conf = [];

	/**
	 * セット
	*/
	public static function set(string $k, $v): void{
		self::$conf[$k] = $v;
	}
	/**
	 * ゲット
	 */
	public static function get(string $name, $default=null){
		if(isset(self::$conf[$name])){
			return self::$conf[$name];
		}
		return $default;
	}
	/**
	 * セットされているか
	 */
	public static function has(string $name): bool{
		return array_key_exists($name,self::$conf);
	}
	/**
	 * 設定ファイルのパス
	 */
	public static function settings_path(string $name): string{
		$d = debug_backtrace(false);
		$d = array_pop($d);
		$dir = str_replace('phar://','',dirname($d['file']));
		
		if(!is_dir($dir)){
			throw new \InvalidArgumentException('not found '.$dir);
		}
		return $dir.'/'.$name;
	}
	/**
	 * 設定ファイル/ディレクトリが存在するか
	 */
	public static function has_settings(string $name): ?string{
		try{
			$path = self::settings_path('testman.'.$name);
			return (is_file($path) || is_dir($path) ? $path : null);
		}catch(\InvalidArgumentException $e){
		}
		return null;
	}
}