<?php
namespace testman;
/**
 * HTTP接続クラス
 * @author tokushima
 *
 */
class Browser{
	private $resource;
	private $agent;
	private $timeout = 30;
	private $redirect_max = 20;
	private $redirect_count = 1;

	private $request_header = [];
	private $request_vars = [];
	private $request_file_vars = [];
	private $head;
	private $body;
	private $cookie = [];
	private $url;
	private $status;
	
	private $user;
	private $password;
	private $bearer_token;
	
	private $raw;
	
	private static $recording_request = false;
	private static $record_request = [];
	
	public function __construct($agent=null,$timeout=30,$redirect_max=20){
		$this->agent = $agent;
		$this->timeout = (int)$timeout;
		$this->redirect_max = (int)$redirect_max;
	}
	/**
	 * 最大リダイレクト回数を設定
	 * @param integer $redirect_max
	 */
	public function redirect_max($redirect_max){
		$this->redirect_max = (integer)$redirect_max;
		return $this;
	}
	/**
	 * タイムアウト時間を設定
	 * @param integer $timeout
	 * @return $this
	 */
	public function timeout($timeout){
		$this->timeout = (int)$timeout;
		return $this;
	}
	/**
	 * ユーザエージェントを設定
	 * @param string $agent
	 * @return $this
	 */
	public function agent($agent){
		$this->agent = $agent;
		return $this;
	}
	/**
	 * Basic認証
	 * @param string $user ユーザ名
	 * @param string $password パスワード
	 * @return $this
	 */
	public function basic($user,$password){
		$this->user = $user;
		$this->password = $password;
		return $this;
	}
	/**
	 * Bearer token
	 * @param string $token
	 */
	public function bearer_token($token){
		$this->bearer_token = $token;
	}
	
	public function __toString(){
		return $this->body();
	}
	/**
	 * ヘッダを設定
	 * @param string $key
	 * @param string $value
	 * @return $this
	 */
	public function header($key,$value=null){
		$this->request_header[$key] = $value;
		return $this;
	}
	
	/**
	 * ACCEPT=application/debugを設定する
	 * @return $this
	 */
	public function set_header_accept_debug(){
		return $this->header('Accept','application/debug');
	}
	/**
	 * ACCEPT=application/jsonを設定する
	 * @return $this
	 */
	public function set_header_accept_json(){
		return $this->header('Accept','application/json');
	}
	
	/**
	 * クエリを設定
	 * @param string $key
	 * @param string $value
	 * @return $this
	 */
	public function vars($key,$value=null){
		if(is_bool($value)){
			$value = ($value) ? 'true' : 'false';
		}
		$this->request_vars[$key] = $value;
		
		if(isset($this->request_file_vars[$key])){
			unset($this->request_file_vars[$key]);
		}
		return $this;
	}
	/**
	 * クエリにファイルを設定
	 * @param string $key
	 * @param string $filename
	 * @return $this
	 */
	public function file_vars($key,$filename){
		$this->request_file_vars[$key] = $filename;
		
		if(isset($this->request_vars[$key])){
			unset($this->request_vars[$key]);
		}
		return $this;
	}
	/**
	 * クエリが設定されているか
	 * @param string $key
	 * @return $this
	 */
	public function has_vars($key){
		return (array_key_exists($key,$this->request_vars) || array_key_exists($key,$this->request_file_vars));
	}
	/**
	 * cURL 転送用オプションを設定する
	 * @param string $key
	 * @param mixed $value
	 * @return $this
	 */
	public function setopt($key,$value){
		if(!isset($this->resource)){
			$this->resource = curl_init();
		}
		curl_setopt($this->resource,$key,$value);
		return $this;
	}
	
	/**
	 * 結果のヘッダを取得
	 * @return string
	 */
	public function response_headers(){
		return $this->head;
	}
	/**
	 * クッキーを取得
	 * @return mixed{}
	 */
	public function cookies(){
		return $this->cookie;
	}
	/**
	 * 結果の本文を取得
	 * @return string
	 */
	public function body(){
		return ($this->body === null || is_bool($this->body)) ? '' : $this->body;
	}
	/**
	 * 結果のURLを取得
	 * @return string
	 */
	public function url(){
		return $this->url;
	}
	/**
	 * 結果のステータスを取得
	 * @return integer
	 */
	public function status(){
		return empty($this->status) ? null : (int)$this->status;
	}
	/**
	 * HEADリクエスト
	 * @param string $url
	 * @return $this
	 */
	public function do_head($url){
		return $this->request('HEAD',$url);
	}
	/**
	 * PUTリクエスト
	 * @param string $url
	 * @return $this
	 */
	public function do_put($url){
		return $this->request('PUT',$url);
	}
	/**
	 * DELETEリクエスト
	 * @param string $url
	 * @return $this
	 */
	public function do_delete($url){
		return $this->request('DELETE',$url);
	}
	/**
	 * GETリクエスト
	 * @param string $url
	 * @return $this
	 */
	public function do_get($url){
		return $this->request('GET',$url);
	}
	/**
	 * POSTリクエスト
	 * @param string $url
	 * @return $this
	 */
	public function do_post($url){
		return $this->request('POST',$url);
	}
	/**
	 * POSTリクエスト(RAW)
	 * @param string $url
	 * @return $this
	 */
	public function do_raw($url,$value){
		$this->raw = $value;
		return $this->request('RAW',$url);
	}
	/**
	 * POSTリクエスト(JSON)
	 * @param string $url
	 * @return $this
	 */
	public function do_json($url){
		$this->header('Content-Type','application/json');
		return $this->do_raw($url,json_encode($this->request_vars));
	}
	/**
	 * GETリクエストでダウンロードする
	 * @param string $url
	 * @param string $filename
	 */
	public function do_download($url,$filename){
		return $this->request('GET',$url,$filename);
	}
	/**
	 * POSTリクエストでダウンロードする
	 * @param string $url
	 * @param string $filename
	 */
	public function do_post_download($url,$filename){
		return $this->request('POST',$url,$filename);
	}
	/**
	 * ヘッダ情報をハッシュで取得する
	 * @return string{}
	 */
	public function explode_head(){
		$result = [];
		foreach(explode("\n",$this->head) as $h){
			if(preg_match("/^(.+?):(.+)$/",$h,$match)) $result[trim($match[1])] = trim($match[2]);
		}
		return $result;
	}
	/**
	 * ヘッダデータを書き込む処理
	 * @param resource $resource
	 * @param string $data
	 * @return number
	 */
	private function callback_head($resource,$data){
		$this->head .= $data;
		return strlen($data);
	}
	/**
	 * データを書き込む処理
	 * @param resource $resource
	 * @param string $data
	 * @return number
	 */
	private function callback_body($resource,$data){
		$this->body .= $data;
		return strlen($data);
	}
	/**
	 * 送信たリクエストの記録を開始する
	 * @return string[]
	 */
	public static function start_record(){
		self::$recording_request = true;
		
		$requests = self::$record_request;
		self::$record_request = [];
		return $requests;
	}
	/**
	 * 送信したリクエストの記録を終了する
	 * @return string[]
	 */
	public static function stop_record(){
		self::$recording_request = false;
		return self::$record_request;
	}
	private function request($method,$url,$download_path=null){
		if(!isset($this->resource)){
			$this->resource = curl_init();
		}
		
		$url = \testman\Util::url($url);
		$url_info = parse_url($url);
		$cookie_base_domain = (isset($url_info['host']) ? $url_info['host'] : '').(isset($url_info['path']) ? $url_info['path'] : '');

		// set coverage query
		if(\testman\Coverage::has_link($vars)){
			foreach(\testman\Conf::get('urls',[]) as $u){
				if(preg_match('/'.str_replace('%s','.+',preg_quote($u,'/')).'/',$url)){
					$this->request_vars[\testman\Coverage::link()] = $vars;
					break;
				}
			}
		}
		switch($method){
			case 'RAW':
			case 'POST': curl_setopt($this->resource,CURLOPT_POST,true); break;
			case 'GET':
				if(isset($url_info['query'])){
					parse_str($url_info['query'],$vars);
				
					foreach($vars as $k => $v){
						if(!isset($this->request_vars[$k])){
							$this->request_vars[$k] = $v;
						}
					}
					list($url) = explode('?',$url,2);
				}
				curl_setopt($this->resource,CURLOPT_HTTPGET,true);
				break;
			case 'HEAD': curl_setopt($this->resource,CURLOPT_NOBODY,true); break;
			case 'PUT': curl_setopt($this->resource,CURLOPT_PUT,true); break;
			case 'DELETE': curl_setopt($this->resource,CURLOPT_CUSTOMREQUEST,'DELETE'); break;
		}
		switch($method){
			case 'POST':
				if(!empty($this->request_file_vars)){
					$vars = [];
					if(!empty($this->request_vars)){
						foreach(explode('&',http_build_query($this->request_vars)) as $q){
							$s = explode('=',$q,2);
							$vars[urldecode($s[0])] = isset($s[1]) ? urldecode($s[1]) : null;
						}
					}
					foreach(explode('&',http_build_query($this->request_file_vars)) as $q){
						$s = explode('=',$q,2);
						
						if(isset($s[1])){
							if(!is_file($f=urldecode($s[1]))){
								throw new \RuntimeException($f.' not found');
							}
							$vars[urldecode($s[0])] = (class_exists('\\CURLFile',false)) ? new \CURLFile($f) : '@'.$f;
						}
					}
					curl_setopt($this->resource,CURLOPT_POSTFIELDS,$vars);
				}else{
					curl_setopt($this->resource,CURLOPT_POSTFIELDS,http_build_query($this->request_vars));
				}
				break;
			case 'RAW':
				if(!isset($this->request_header['Content-Type'])){
					$this->request_header['Content-Type'] = 'text/plain';
				}
				curl_setopt($this->resource,CURLOPT_POSTFIELDS,$this->raw);
				break;
			case 'GET':
			case 'HEAD':
			case 'PUT':
			case 'DELETE':
				$url = $url.(!empty($this->request_vars) ? '?'.http_build_query($this->request_vars) : '');
		}
		curl_setopt($this->resource,CURLOPT_URL,$url);
		curl_setopt($this->resource,CURLOPT_FOLLOWLOCATION,false);
		curl_setopt($this->resource,CURLOPT_HEADER,false);
		curl_setopt($this->resource,CURLOPT_RETURNTRANSFER,false);
		curl_setopt($this->resource,CURLOPT_FORBID_REUSE,true);
		curl_setopt($this->resource,CURLOPT_FAILONERROR,false);
		curl_setopt($this->resource,CURLOPT_TIMEOUT,$this->timeout);
		
		if(self::$recording_request){
			curl_setopt($this->resource,CURLINFO_HEADER_OUT,true);
		}
		if(!empty($this->user)){
			curl_setopt($this->resource,CURLOPT_USERPWD,$this->user.':'.$this->password);
		}else if(!empty($this->bearer_token)){
			$this->request_header['Authorization'] = 'Bearer '.$this->bearer_token;
		}
		if(!isset($this->request_header['Expect'])){
			$this->request_header['Expect'] = null;
		}
		if(!empty($this->cookie)){
			$cookies = '';
			$now = time();
			
			foreach($this->cookie as $domain => $cookieval){
				if(strpos($cookie_base_domain,$domain) === 0 || strpos($cookie_base_domain,(($domain[0] == '.') ? $domain : '.'.$domain)) !== false){
					foreach($cookieval as $k => $v){
						if(!empty($v['expires']) && $v['expires'] < $now){
							unset($this->cookie[$domain][$k]);
						}else if(!$v['secure'] || ($v['secure'] && substr($url,0,8) == 'https://')){
							$cookies .= sprintf('%s=%s; ',$k,$v['value']);
						}
					}
				}
			}
			curl_setopt($this->resource,CURLOPT_COOKIE,$cookies);
		}
		if(!isset($this->request_header['User-Agent'])){
			curl_setopt($this->resource,CURLOPT_USERAGENT,
				(empty($this->agent) ?
					(isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null) :
					$this->agent
				)
			);
		}
		curl_setopt($this->resource,CURLOPT_HTTPHEADER,
			array_map(
				function($k,$v){
					return $k.': '.$v;
				},
				array_keys($this->request_header),
				$this->request_header
			)
		);
		curl_setopt($this->resource,CURLOPT_HEADERFUNCTION,[$this,'callback_head']);
		
		if(empty($download_path)){
			curl_setopt($this->resource,CURLOPT_WRITEFUNCTION,[$this,'callback_body']);
		}else{
			if(strpos($download_path,'://') === false && !is_dir(dirname($download_path))){
				mkdir(dirname($download_path),0777,true);
			}
			$fp = fopen($download_path,'wb');
			
			curl_setopt($this->resource,CURLOPT_WRITEFUNCTION,function($resource,$data) use(&$fp){
				if($fp){
					fwrite($fp,$data);
				}
				return strlen($data);
			});
		}
		$this->request_header = $this->request_vars = $this->request_file_vars = [];
		$this->head = $this->body = $this->raw = '';
		$this->bearer_token = $this->user = $this->password = null;
		
		curl_exec($this->resource);
		
		if(!empty($download_path) && $fp){
			fclose($fp);
		}
		if(($err_code = curl_errno($this->resource)) > 0){
			if($err_code == 47 || $err_code == 52){
				return $this;
			}
			throw new \RuntimeException($err_code.': '.curl_error($this->resource));
		}
		$this->url = curl_getinfo($this->resource,CURLINFO_EFFECTIVE_URL);
		$this->status = curl_getinfo($this->resource,CURLINFO_HTTP_CODE);

		if(self::$recording_request){
			self::$record_request[] = curl_getinfo($this->resource,CURLINFO_HEADER_OUT);
		}
		
		// remove coverage query
		if(strpos($this->url,'?') !== false){
			list($url,$query) = explode('?',$this->url,2);
			if(!empty($query)){
				parse_str($query,$vars);
				
				if(array_key_exists(\testman\Coverage::link(),$vars)){
					unset($vars[\testman\Coverage::link()]);
				}
				if(!empty($vars)){
					$url = $url.'?'.http_build_query($vars);
				}
			}
			$this->url = $url;
		}
		
		if(preg_match_all('/Set-Cookie:[\s]*(.+)/i',$this->head,$match)){
			foreach($match[1] as $cookies){
				$cookie_name = $cookie_value = $cookie_domain = $cookie_path = $cookie_expires = null;
				$cookie_domain = $cookie_base_domain;
				$cookie_path = '/';
				$secure = false;
				
				foreach(explode(';',$cookies) as $cookie){
					$cookie = trim($cookie);
					if(strpos($cookie,'=') !== false){
						list($k,$v) = explode('=',$cookie,2);
						$k = trim($k);
						$v = trim($v);
						
						switch(strtolower($k)){
							case 'expires': $cookie_expires = ctype_digit($v) ? (int)$v : strtotime($v); break;
							case 'domain': $cookie_domain = preg_replace('/^[\w]+:\/\/(.+)$/','\\1',$v); break;
							case 'path': $cookie_path = $v; break;
							default:
								if(!isset($cookie_name)){
									$cookie_name = $k;
									$cookie_value = $v;
								}
						}
					}else if(strtolower($cookie) == 'secure'){
						$secure = true;
					}
				}
				$cookie_domain = substr(\testman\Util::path_absolute('http://'.$cookie_domain,$cookie_path),7);
				
				if($cookie_expires !== null && $cookie_expires < time()){
					if(isset($this->cookie[$cookie_domain][$cookie_name])){
						unset($this->cookie[$cookie_domain][$cookie_name]);
					}
				}else{
					$this->cookie[$cookie_domain][$cookie_name] = ['value'=>$cookie_value,'expires'=>$cookie_expires,'secure'=>$secure];
				}
			}
		}
		curl_close($this->resource);
		unset($this->resource);
		
		if($this->redirect_count++ < $this->redirect_max){
			switch($this->status){
				case 300:
				case 301:
				case 302:
				case 303:
				case 307:
					if(preg_match('/Location:[\040](.*)/i',$this->head,$redirect_url)){
						return $this->request('GET',trim(\testman\Util::path_absolute($url,$redirect_url[1])),$download_path);
					}
			}
		}
		$this->redirect_count = 1;
		return $this;
	}
	public function __destruct(){
		if(isset($this->resource)){
			curl_close($this->resource);
		}
	}
	/**
	 * bodyを解析しXMLオブジェクトとして返す
	 * @return \testman\Xml
	 */
	public function xml($name=null){
		try{
			return \testman\Xml::extract($this->body(),$name);
		}catch(\testman\NotFoundException $e){
			throw new \testman\NotFoundException($e->getMessage().': '.substr($this->body(),0,100).((strlen($this->body()) > 100) ? '..' : ''));
		}
	}
	/**
	 * bodyを解析し配列として返す
	 * @param string $name
	 * @return mixed{}
	 */
	public function json($name=null){
		$json = new \testman\Json($this->body());
		return $json->find($name);
	}
	
	/**
	 * エラーがあるか
	 * @param \testman\Browser $b
	 * @param string $type
	 * @return boolean
	 */
	public function has_error($type){
		$func = \testman\Conf::get('browser_has_error_func');
		
		if(is_callable($func)){
			if($func($b,$type)){
				return;
			}
		}else{
			if(substr(trim($this->body()),0,1) == '{'){
				$errors = $this->json('error');
				
				if(is_array($errors)){
					foreach($errors as $err){
						if($err['type'] == $type){
							return;
						}
					}
				}
			}else{
				foreach($this->xml('error')->find('message') as $message){
					if($message->in_attr('type') == $type){
						return;
					}
				}
			}
		}
		throw new \testman\NotFoundException($type.' not found, '.(substr($this->body(),0,100).((strlen($this->body()) > 100) ? '..' : '')));
	}
	
	/**
	 * bodyから探す
	 * @param string $name
	 * @return mixed
	 */
	public function find_get($name){
		$func = \testman\Conf::get('browser_find_func');
		
		if(is_callable($func)){
			return $func($b,$name);
		}else{
			if(substr(trim($this->body()),0,1) == '{'){
				return $this->json($name);
			}else{
				return $this->xml($name)->children();
			}
		}
	}
}
