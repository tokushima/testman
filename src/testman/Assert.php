<?php
namespace testman;

class Assert{
	/**
	 * 変数を展開していく
	 */
	public static function expvar($var){
		if(is_numeric($var)){
			return strval($var);
		}
		if(is_object($var)){
			$var = get_object_vars($var);
		}
		if(is_array($var)){
			foreach($var as $key => $v){
				$var[$key] = self::expvar($v);
			}
		}
		return $var;
	}
}
