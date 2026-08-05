<?php
namespace testman;

class Std{
	private static bool $stdout = true;
	private static ?int $cols = null;

	/**
	 * 標準出力に表示するか
	 */
	public static function disp(bool $bool){
		self::$stdout = $bool;
	}
	/**
	 * ターミナルの桁数（横幅）を取得する。
	 *
	 * TTYでない場合や取得できない場合は 0 を返す（＝折り返し対策不要）。
	 */
	public static function cols(): int{
		if(self::$cols === null){
			self::$cols = self::detect_cols();
		}
		return self::$cols;
	}
	private static function detect_cols(): int{
		// パイプ/リダイレクト時は折り返しの問題は起きないので対象外
		if(function_exists('stream_isatty') && defined('STDOUT') && !@stream_isatty(STDOUT)){
			return 0;
		}
		$env = getenv('COLUMNS');
		if($env !== false && (int)$env > 0){
			return (int)$env;
		}
		$tput = @shell_exec('tput cols 2>/dev/null');
		if($tput !== null && (int)$tput > 0){
			return (int)$tput;
		}
		$stty = @shell_exec('stty size 2>/dev/null');
		if($stty !== null && preg_match('/\d+\s+(\d+)/', $stty, $m) && (int)$m[1] > 0){
			return (int)$m[1];
		}
		return 0;
	}
	/**
	 * ANSIエスケープ・マルチバイトを考慮して表示幅で切り詰める。
	 *
	 * エスケープシーケンスは幅にカウントせずそのまま保持し、切り詰めた場合は
	 * 末尾にリセット(\033[0m)を付与して色が残らないようにする。
	 */
	public static function truncate_visible(string $str, int $max_width): string{
		if($max_width <= 0){
			return '';
		}
		$len = strlen($str);
		$out = '';
		$width = 0;
		$i = 0;
		$truncated = false;
		while($i < $len){
			if($str[$i] === "\033"){
				$j = $i + 1;
				if($j < $len && $str[$j] === '['){
					$j++;
					while($j < $len && !ctype_alpha($str[$j])){
						$j++;
					}
					if($j < $len){
						$j++; // 終端の英字を含める
					}
				}
				$out .= substr($str, $i, $j - $i);
				$i = $j;
				continue;
			}
			$c = ord($str[$i]);
			if($c < 0x80){
				$clen = 1;
			}elseif($c < 0xE0){
				$clen = 2;
			}elseif($c < 0xF0){
				$clen = 3;
			}else{
				$clen = 4;
			}
			$char = substr($str, $i, $clen);
			$cw = function_exists('mb_strwidth') ? mb_strwidth($char, 'UTF-8') : 1;
			if($width + $cw > $max_width){
				$truncated = true;
				break;
			}
			$out .= $char;
			$width += $cw;
			$i += $clen;
		}
		if($truncated){
			$out .= "\033[0m";
		}
		return $out;
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
