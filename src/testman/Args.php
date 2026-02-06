<?php
namespace testman;

class Args{
	static private array $opt = [];
	static private array $value = [];

	/**
	 * 初期化
	*/
	public static function init(): void{
		$opt = $value = [];
		$argv = array_slice((isset($_SERVER['argv']) ? $_SERVER['argv'] : []),1);
			
		for($i=0;$i<sizeof($argv);$i++){
			if(substr($argv[$i],0,2) == '--'){
				$opt[substr($argv[$i],2)][] = ((isset($argv[$i+1]) && $argv[$i+1][0] != '-') ? $argv[++$i] : true);
			}else if(substr($argv[$i],0,1) == '-'){
				$keys = str_split(substr($argv[$i],1),1);
				if(count($keys) == 1){
					$opt[$keys[0]][] = ((isset($argv[$i+1]) && $argv[$i+1][0] != '-') ? $argv[++$i] : true);
				}else{
					foreach($keys as $k){
						$opt[$k][] = true;
					}
				}
			}else{
				$value[] = $argv[$i];
			}
		}
		self::$opt = $opt;
		self::$value = $value;
	}
	/**
	 * オプション値の取得
	 */
	public static function opt(string $name, $default=false){
		return array_key_exists($name,self::$opt) ? self::$opt[$name][0] : $default;
	}
	/**
	 * オプションが宣言されたか
	 */
	public static function has_opt(string $name): bool{
		return array_key_exists($name,self::$opt);		
	}
	/**
	 * 引数の取得
	 */
	public static function value($default = null): ?string{
		return self::$value[0] ?? $default;
	}
	/**
	 * オプション値を配列として取得
	 */
	public static function opts(string $name){
		return array_key_exists($name,self::$opt) ? self::$opt[$name] : [];
	}
	/**
	 * 引数を全て取得
	 */
	public static function values(){
		return self::$value;
	}
}
