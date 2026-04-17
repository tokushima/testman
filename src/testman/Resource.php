<?php
namespace testman;

class Resource{
	public static function path(string $file): string{
		// 事前設定されたパスを優先（並列実行用）
		$dir = \testman\Conf::get('resources_dir');
		if($dir === null){
			$dir = \testman\Conf::find_settings_path('resources');
		}

		if($dir !== null && (is_file($f=$dir.'/'.$file) || is_dir($f=$dir.'/'.$file))){
			return realpath($f);
		}

		if($dir !== null){
			throw new \testman\NotFoundException($dir.'/'.$file.' not found');
		}

		$searched = [];
		$conf_dir = \testman\Conf::get('resources_dir');
		if($conf_dir !== null){
			$searched[] = $conf_dir;
		}
		foreach(\testman\Conf::settings_search_paths('resources') as $p){
			$searched[] = $p;
		}

		throw new \testman\NotFoundException(
			$file.' not found (searched: '.implode(', ', $searched).')'
		);
	}
}

