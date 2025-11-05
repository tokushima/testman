<?php
namespace testman;
/**
 * JSON 文字列を操作する
 */
class Json{
	private $arr = [];
	
	/**
	 * JSONからオブジェクトを生成する
	 */
	public function __construct(string $json){
		$this->arr = self::decode($json);
	}
	/**
	 * パスから値を取得する
	 */
	public function find(?string $name=null){
		if(empty($name)){
			return $this->arr;
		}
		$names = explode('/',$name);
		$arr = $this->arr;
		
		foreach($names as $key){
			if(is_array($arr) && array_key_exists($key,$arr)){
				$arr = $arr[$key];
			}else{
				throw new \testman\NotFoundException();
			}
		}
		return $arr;
	}
	
	/**
	 * 値を JSON 形式にして返す
	 */
	public static function encode($val, bool $format=false): string{
		$json = ($format) ?
			json_encode(self::encode_object($val),JSON_PRETTY_PRINT) :
			json_encode(self::encode_object($val));
		
		if(json_last_error() != JSON_ERROR_NONE){
			throw new \InvalidArgumentException(json_last_error_msg());
		}
		return $json;
	}
	
	private static function encode_object($val): array{
		if(is_object($val) || is_array($val)){
			$rtn = [];
			
			foreach($val as $k => $v){
				$rtn[$k] = self::encode_object($v);
			}
			return $rtn;
		}
		return $val;
	}
	/**
	 * JSON 文字列をデコードする
	 */
	public static function decode(string $json){
		if(is_null($json) || $json === ''){
			return null;
		}
		$val = json_decode($json,true);
		
		if(json_last_error() != JSON_ERROR_NONE){
			throw new \InvalidArgumentException(json_last_error_msg());
		}
		return $val;
	}
}