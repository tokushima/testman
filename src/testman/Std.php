<?php
namespace testman;

class Std{
	private static bool $stdout = true;

	/**
	 * 標準出力に表示するか
	 */
	public static function disp(bool $bool){
		self::$stdout = $bool;
	}
	/**
	 * 色付きでプリント
	 */
	public static function p(string $msg, string $color='0'){
		if(self::$stdout){
			print("\033[".$color."m".$msg."\033[0m");
		}
	}
	/**
	 * カーソルを移動
	 */
	public static function cur(int $up_down, int $left_right): void{
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
	public static function line_clear(): void{
		print("\033[2K");
	}
	/**
	 * BackSpace
	 */
	public static function bs(int $num=0): void{
		if(\testman\Conf::get('stdbs',true) === false){
			print(PHP_EOL);
		}else{
			if(empty($num)){
				print("\033[2K");
			}else{
				self::cur(0,$num*-1);
				print(str_repeat(' ',$num));
				self::cur(0,$num*-1);
			}
		}
	}
	/**
	 * 改行つきで色付きでプリント
	 */
	public static function println(string $msg='', string $ansi_color='0'){
		self::p($msg.PHP_EOL,$ansi_color);
	}
	/**
	 * White
	 */
	public static function println_white(string $msg){
		self::println($msg,'37');
	}
	/**
	 * Blue
	 * @param string $msg
	 */
	public static function println_primary(string $msg){
		self::println($msg,'1;34');
	}
	/**
	 * Green
	 * @param string $msg
	 */
	public static function println_success(string $msg){
		self::println($msg,'32');
	}
	/**
	 * Cyan
	 * @param string $msg
	 */
	public static function println_info(string $msg){
		self::println($msg,'36');
	}
	/**
	 * Yellow
	 * @param string $msg
	 */
	public static function println_warning(string $msg){
		self::println($msg,'33');
	}
	/**
	 * Red
	 * @param string $msg
	 */
	public static function println_danger(string $msg){
		self::println($msg,'31');
	}
}
