<?php
namespace testman;

class Browser{
	private $resource; // resource|false|CurlHandle
	private ?string $agent;
	private int $timeout = 30;
	private int $redirect_max = 20;
	private int $redirect_count = 1;

	private array $request_header = [];
	private array $request_vars = [];
	private array $request_file_vars = [];
	private string $head;
	private string $body = '';
	private array $cookie = [];
	private string $url;
	private int $status;
	
	private ?string $user;
	private ?string $password;
	private ?string $bearer_token;

	private ?array $proxy;
	private bool $ssl_verify = true;
	
	private string $raw;
	
	private static bool $recording_request = false;
	private static array $record_request = [];
	
	private static array $debug_filepath = [];
	
	public static function debug(bool $debug_mode=true): void{
		$backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
		if(isset($backtrace[0]['file'])){
			self::$debug_filepath[$backtrace[0]['file']] = $debug_mode;
		}
	}

	public function __construct(?string $agent=null, int $timeout=30, int $redirect_max=20){
		$this->agent = $agent;
		$this->timeout = (int)$timeout;
		$this->redirect_max = (int)$redirect_max;
	}
	/**
	 * 最大リダイレクト回数を設定
	 */
	public function redirect_max(int $redirect_max){
		$this->redirect_max = $redirect_max;
		return $this;
	}
	/**
	 * タイムアウト時間を設定
	 */
	public function timeout(int $timeout): self{
		$this->timeout = $timeout;
		return $this;
	}
	/**
	 * ユーザエージェントを設定
	 */
	public function agent(string $agent): self{
		$this->agent = $agent;
		return $this;
	}
	/**
	 * Basic認証
	 */
	public function basic(string $user, string $password): self{
		$this->user = $user;
		$this->password = $password;
		return $this;
	}
	/**
	 * Bearer token
	 */
	public function bearer_token(string $token): self{
		$this->bearer_token = $token;
		return $this;
	}
	
	public function __toString(){
		return $this->body();
	}
	/**
	 * ヘッダを設定
	 */
	public function header(string $key, ?string $value=null): self{
		$this->request_header[$key] = $value;
		return $this;
	}
	
	/**
	 * ACCEPT=application/debugを設定する
	 */
	public function set_header_accept_debug(): self{
		return $this->header('Accept','application/debug');
	}
	/**
	 * ACCEPT=application/jsonを設定する
	 */
	public function set_header_accept_json(): self{
		return $this->header('Accept','application/json');
	}
	/**
	 * ACCEPTを指定しない
	 */
	public function set_header_accept_none(): self{
		return $this->header('Accept','*/*');
	}
	
	/**
	 * クエリを設定
	 */
	public function vars(string $key, $value=null): self{
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
	 */
	public function file_vars(string $key, string $filename): self{
		$this->request_file_vars[$key] = $filename;
		
		if(isset($this->request_vars[$key])){
			unset($this->request_vars[$key]);
		}
		return $this;
	}
	/**
	 * クエリが設定されているか
	 */
	public function has_vars(string $key): bool{
		return (
			array_key_exists($key, $this->request_vars) || 
			array_key_exists($key,$this->request_file_vars)
		);
	}
	/**
	 * cURL 転送用オプションを設定する
	 */
	public function setopt(string $key, $value): self{
		if(!isset($this->resource)){
			$this->resource = curl_init();
		}
		curl_setopt($this->resource,$key,$value);
		return $this;
	}
	
	/**
	 * 結果のヘッダを取得
	 */
	public function response_headers(): string{
		return $this->head;
	}
	/**
	 * クッキーを取得
	 */
	public function cookies(): array{
		return $this->cookie;
	}
	/**
	 * 結果の本文を取得
	 */
	public function body(): string{
		return ($this->body === null || is_bool($this->body)) ? '' : $this->body;
	}
	/**
	 * 結果のURLを取得
	 */
	public function url(): string{
		return $this->url;
	}
	/**
	 * 結果のステータスを取得
	 */
	public function status(): int{
		return empty($this->status) ? 0 : (int)$this->status;
	}
	/**
	 * HEADリクエスト
	 * @param string|array $url
	 */
	public function do_head($url): self{
		return $this->request('HEAD',$url);
	}
	/**
	 * PUTリクエスト
	 * @param string|array $url
	 */
	public function do_put($url): self{
		return $this->request('PUT',$url);
	}
	/**
	 * DELETEリクエスト
	 * @param string|array $url
	 */
	public function do_delete($url): self{
		return $this->request('DELETE',$url);
	}
	/**
	 * GETリクエスト
	 * @param string|array $url
	 */
	public function do_get($url): self{
		return $this->request('GET',$url);
	}
	/**
	 * POSTリクエスト
	 * @param string|array $url
	 */
	public function do_post($url): self{
		return $this->request('POST',$url);
	}
	/**
	 * POSTリクエスト(RAW)
	 * @param string|array $url
	 */
	public function do_raw($url, string $value): self{
		$this->raw = $value;
		return $this->request('RAW',$url);
	}
	/**
	 * POSTリクエスト(JSON)
	 * @param string|array $url
	 */
	public function do_json($url): self{
		$this->header('Content-Type','application/json');
		return $this->do_raw($url,json_encode($this->request_vars));
	}
	/**
	 * GETリクエストでダウンロードする
	 * @param string|array $url
	 */
	public function do_download($url, string $filename): self{
		return $this->request('GET',$url,$filename);
	}
	/**
	 * POSTリクエストでダウンロードする
	 * @param string|array $url
	 */
	public function do_post_download($url, string $filename): self{
		return $this->request('POST',$url,$filename);
	}
	/**
	 * ヘッダ情報をハッシュで取得する
	 */
	public function explode_head(): array{
		$result = [];
		foreach(explode("\n",$this->head) as $h){
			if(preg_match("/^(.+?):(.+)$/",$h,$match)) $result[trim($match[1])] = trim($match[2]);
		}
		return $result;
	}
	/**
	 * ヘッダデータを書き込む処理
	 */
	private function callback_head($resource, string $data): int{
		$this->head .= $data;
		return strlen($data);
	}
	/**
	 * データを書き込む処理
	 */
	private function callback_body($resource, string $data): int{
		$this->body .= $data;
		return strlen($data);
	}
	/**
	 * 送信たリクエストの記録を開始する
	 */
	public static function start_record(): array{
		self::$recording_request = true;
		
		$requests = self::$record_request;
		self::$record_request = [];
		return $requests;
	}
	/**
	 * 送信したリクエストの記録を終了する
	 */
	public static function stop_record(): array{
		self::$recording_request = false;
		return self::$record_request;
	}

	private static function url_rewrite(string $url): string{
		$rewrite = \testman\Conf::get('url_rewrite', []);
		
		if(!empty($rewrite)){
			[$base_url, $query] = (strpos($url, '?') === false) ? [$url, ''] : explode('?', $url, 2);

			foreach($rewrite as $pattern => $replacement){
				$subject = (strpos($pattern, '\?') === false) ? $base_url : $url;

				if(!empty($pattern) && preg_match($pattern, $subject, $matches)){	
					$new_url_params = [];

					if(preg_match_all('/(\/%[0-9s]+)/', $replacement, $param_matches)){
						$match_params = array_slice($matches, 1);

						foreach($param_matches[0] as $i => $param_match){
							$idx = ($param_match == 's') ? $i : (int)substr($param_match, 2);
							$new_url_params[$idx] = $match_params[$idx] ?? '';

							$replacement = str_replace($param_match, '', $replacement);
						}
					}
					$new_url = preg_replace($pattern, $replacement, $subject);
					if(strpos($new_url, '?') !== false){
						[$new_url, $new_query] = explode('?', $new_url, 2);
						$query = $query.(empty($query) ? '' : '&').$new_query;
					}
					$new_url = \testman\Util::url(empty($new_url_params) ? $new_url : array_merge([$new_url], $new_url_params));
					$new_url = $new_url.(empty($query) ? '' : ((strpos($new_url, '?') === false) ? '?' : '&').$query);
					\testman\Conf::log_debug_callback('URL rewrite (testman): '.$url.' to '.$new_url);

					return $new_url;
				}
			}
		}
		return $url;
	}

	/**
	 * @param string|array $url
	 */
	private function request(string $method, $url, ?string $download_path=null){
		$backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
		if(
			(isset($backtrace[2]['file']) && (self::$debug_filepath[$backtrace[2]['file']] ?? false)) ||
			(isset($backtrace[1]['file']) && (self::$debug_filepath[$backtrace[1]['file']] ?? false))			
		){
			$this->set_header_accept_debug();
		}

		if(!isset($this->resource)){
			$this->resource = curl_init();
		}
		
		$url = \testman\Util::url($url);
		$url = self::url_rewrite($url);

		$url_info = parse_url($url);
		$cookie_base_domain = (isset($url_info['host']) ? $url_info['host'] : '').(isset($url_info['path']) ? $url_info['path'] : '');

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

		/**
		 * @param bool $ssl_verify SSL証明書を確認するかの真偽値
		 */
		if($this->ssl_verify === false || \testman\Conf::get('ssl-verify',true) === false){
			curl_setopt($this->resource, CURLOPT_SSL_VERIFYHOST,false);
			curl_setopt($this->resource, CURLOPT_SSL_VERIFYPEER,false);
		}
		if(!empty($this->proxy)){
			curl_setopt($this->resource,CURLOPT_HTTPPROXYTUNNEL,true);
			curl_setopt($this->resource,CURLOPT_PROXY,$this->proxy[0]);
			
			if(!empty($this->proxy[1] ?? null)){
				curl_setopt($this->resource,CURLOPT_PROXYPORT,$this->proxy[1]);
			}
		}		
		if(!empty($this->user)){
			curl_setopt($this->resource,CURLOPT_USERPWD,$this->user.':'.$this->password);
		}else if(!empty($this->bearer_token)){
			$this->request_header['Authorization'] = 'Bearer '.$this->bearer_token;
		}
		if(!isset($this->request_header['Expect'])){
			$this->request_header['Expect'] = null;
		}
		if(!array_key_exists('Accept',$this->request_header) && !empty($accept = \testman\Conf::get('accept'))){
			$this->request_header['Accept'] = $accept;
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
	 */
	public function xml(?string $name=null): \testman\Xml{
		try{
			return \testman\Xml::extract($this->body(),$name);
		}catch(\testman\NotFoundException $e){
			throw new \testman\NotFoundException($e->getMessage().': '.substr($this->body(),0,100).((strlen($this->body()) > 100) ? '..' : ''));
		}
	}
	/**
	 * bodyを解析しJSONの結果として返す
	 */
	public function json(?string $name=null){
		$json = new \testman\Json($this->body());
		return $json->find($name);
	}
	
	/**
	 * エラーがあるか
	 */
	public function has_error(string $type): void{
		$func = \testman\Conf::get('browser_has_error_func');
		
		if(is_callable($func)){
			if($func($this, $type)){
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
	 */
	public function find_get(string $name){
		$func = \testman\Conf::get('browser_find_func');
		
		if(is_callable($func)){
			return $func($this, $name);
		}else{
			if(substr(trim($this->body()),0,1) == '{'){
				return $this->json($name);
			}else{
				return $this->xml($name)->children();
			}
		}
	}
}
