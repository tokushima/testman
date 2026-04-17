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
	private static array $found_settings_cache = [];

	public static function find_settings_path(string $name): ?string{
		if(array_key_exists($name, self::$found_settings_cache)){
			return self::$found_settings_cache[$name];
		}
		$result = null;
		foreach(self::settings_search_paths($name) as $path){
			if(is_file($path) || is_dir($path)){
				$result = $path;
				break;
			}
		}
		self::$found_settings_cache[$name] = $result;
		return $result;
	}

	/**
	 * testman.{name} の探索候補パスを返す
	 *
	 * @return string[]
	 */
	public static function settings_search_paths(string $name): array{
		$target = 'testman.'.$name;
		$cwd = self::get_initial_cwd();
		$cwd_real = realpath($cwd) ?: $cwd;
		$start = (self::$base_dir !== null && is_dir(self::$base_dir))
			? (realpath(self::$base_dir) ?: self::$base_dir)
			: $cwd_real;

		$paths = [];
		$dir = $start;
		while(true){
			$paths[] = $dir.'/'.$target;
			if($dir === $cwd_real){
				break;
			}
			$parent = dirname($dir);
			if($parent === $dir){
				break;
			}
			$dir = $parent;
		}
		return $paths;
	}
	private static ?string $base_dir = null;
	private static ?string $initial_cwd = null;

	/**
	 * settings探索の基準ディレクトリを設定する
	 */
	public static function set_base_dir(string $dir): void{
		self::$base_dir = $dir;
		self::$found_settings_cache = [];
		if(self::$initial_cwd === null){
			self::$initial_cwd = getcwd();
		}
	}

	public static function settings_path(string $name): string{
		if(self::$base_dir !== null && is_dir(self::$base_dir)){
			return self::$base_dir.'/'.$name;
		}
		return self::get_initial_cwd().'/'.$name;
	}

	private static function get_initial_cwd(): string{
		return self::$initial_cwd ?? getcwd();
	}

	public static function log_debug_callback(string $message): void{
		$log_debug_callback = self::get('log_debug_callback');
		if(is_callable($log_debug_callback)){
			call_user_func_array($log_debug_callback, [$message]);
		}
	}
}