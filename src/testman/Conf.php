<?php
namespace testman;

class Conf{
	static private $conf = [];

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