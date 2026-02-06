<?php
namespace testman;

class Resource{
	public static function path(string $file): string{
		// 事前設定されたパスを優先（並列実行用）
		$dir = \testman\Conf::get('resources_dir');
		if($dir === null){
			$dir = \testman\Conf::has_settings('resources');
		}

		if($dir !== null && (is_file($f=$dir.'/'.$file) || is_dir($f=$dir.'/'.$file))){
			return realpath($f);
		}
		throw new \testman\NotFoundException($file.' not found');
	}
}

