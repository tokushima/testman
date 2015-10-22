<?php
namespace testman;

class Std{
	private static $stdout = true;

	/**
	 * 標準出力に表示するか
	 * @param boolean $bool
	 */
	public static function disp($bool){
		self::$stdout = $bool;
	}
	/**
	 * 色付きでプリント
	 * @param string $msg
	 */
	public static function p($msg,$color='0'){
		if(self::$stdout){
			print("\033[".$color."m".$msg."\033[0m");
		}
	}
	/**
	 * カーソルを移動
	 * @param integer $num
	 */
	public static function cur($up_down,$left_right){
		if(!empty($up_down)){
			if($up_down < 0){
				print("\033[".($up_down*-1)."A");
			}else{
				print("\033[".$up_down."B");
			}
		}
		if(!empty($left_right)){
			if($left_right < 0){
				print("\033[".($left_right*-1)."D");
			}else{
				print("\033[".$left_right."C");
			}
		}
	}
	/**
	 * １行削除
	 */
	public static function line_clear(){
		print("\033[2K");
	}
	/**
	 * BackSpace
	 * @param integer $num
	 */
	public static function bs($num=0){
		if(empty($num)){
			print("\033[2K");
		}else{
			self::cur(0,$num*-1);
			print(str_repeat(' ',$num));
			self::cur(0,$num*-1);
		}
	}
	/**
	 * 改行つきで色付きでプリント
	 * @param string $msg
	 * @param string $color ANSI Colors
	 */
	public static function println($msg='',$color='0'){
		self::p($msg.PHP_EOL,$color);
	}
	/**
	 * White
	 * @param string $msg
	 */
	public static function println_white($msg){
		self::println($msg,'37');
	}
	/**
	 * Blue
	 * @param string $msg
	 */
	public static function println_primary($msg){
		self::println($msg,'34');
	}
	/**
	 * Green
	 * @param string $msg
	 */
	public static function println_success($msg){
		self::println($msg,'32');
	}
	/**
	 * Cyan
	 * @param string $msg
	 */
	public static function println_info($msg){
		self::println($msg,'36');
	}
	/**
	 * Yellow
	 * @param string $msg
	 */
	public static function println_warning($msg){
		self::println($msg,'33');
	}
	/**
	 * Red
	 * @param string $msg
	 */
	public static function println_danger($msg){
		self::println($msg,'31');
	}
}
