<?php
namespace testman;

class Resource{
	public static function path($file){
		$dir = \testman\Conf::has_settings('resources');
		if(is_file($f=$dir.'/'.$file)){
			return realpath($f);
		}
		throw new \testman\NotFoundException($file.' not found');
	}
}

