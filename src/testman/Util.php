<?php
namespace testman;

class Util{
	/**
	 * 絶対パスを返す
	 * @param string $a
	 * @param string $b
	 * @return string
	 */
	public static function path_absolute($a,$b){
		if($b === '' || $b === null) return $a;
		if($a === '' || $a === null || preg_match("/^[a-zA-Z]+:/",$b)) return $b;
		if(preg_match("/^[\w]+\:\/\/[^\/]+/",$a,$h)){
			$a = preg_replace("/^(.+?)[".(($b[0] === '#') ? '#' : "#\?")."].*$/","\\1",$a);
			if($b[0] == '#' || $b[0] == '?') return $a.$b;
			if(substr($a,-1) != '/') $b = (substr($b,0,2) == './') ? '.'.$b : (($b[0] != '.' && $b[0] != '/') ? '../'.$b : $b);
			if($b[0] == '/' && isset($h[0])) return $h[0].$b;
		}else if($b[0] == '/'){
			return $b;
		}
		$p = [
			['://','/./','//'],
			['#R#','/','/'],
			["/^\/(.+)$/","/^(\w):\/(.+)$/"],
			["#T#\\1","\\1#W#\\2",''],
			['#R#','#W#','#T#'],
			['://',':/','/']
		];
		$a = preg_replace($p[2],$p[3],str_replace($p[0],$p[1],$a));
		$b = preg_replace($p[2],$p[3],str_replace($p[0],$p[1],$b));
		$d = $t = $r = '';
		if(strpos($a,'#R#')){
			list($r) = explode('/',$a,2);
			$a = substr($a,strlen($r));
			$b = str_replace('#T#','',$b);
		}
		$al = preg_split("/\//",$a,-1,PREG_SPLIT_NO_EMPTY);
		$bl = preg_split("/\//",$b,-1,PREG_SPLIT_NO_EMPTY);

		for($i=0;$i<sizeof($al)-substr_count($b,'../');$i++){
			if($al[$i] != '.' && $al[$i] != '..') $d .= $al[$i].'/';
		}
		for($i=0;$i<sizeof($bl);$i++){
			if($bl[$i] != '.' && $bl[$i] != '..') $t .= '/'.$bl[$i];
		}
		$t = (!empty($d)) ? substr($t,1) : $t;
		$d = (!empty($d) && $d[0] != '/' && substr($d,0,3) != '#T#' && !strpos($d,'#W#')) ? '/'.$d : $d;
		return str_replace($p[4],$p[5],$r.$d.$t);
	}
	
	/**
	 * MAPに従いURLを返す
	 * @param string $url
	 * @return mixed
	 */
	public static function url($url){
		if(is_array($url) || strpos($url,'://') === false){
			$urls = \testman\Conf::get('urls',[]);
			$url_args = [];
			
			if(is_array($url)){
				$url_args = $url;
				$url = array_shift($url_args);
			}
			if(!empty($urls) && isset($urls[$url]) && substr_count($urls[$url],'%s') == sizeof($url_args)){
				$url = vsprintf($urls[$url],$url_args);
			}
		}
		return $url;
	}
}
