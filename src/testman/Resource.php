<?php
namespace testman;

class Resource{
	public static function path(string $file): string{
		$dir = \testman\Conf::has_settings('resources');
		if(is_file($f=$dir.'/'.$file)){
			return realpath($f);
		}
		throw new \testman\NotFoundException($file.' not found');
	}
}

