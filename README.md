#testman

(PHP 5 >= 5.3.0)

![my image](testman.png)

#Download
	$ curl -LO http://git.io/testman.phar

#Quick test

	$ php testman.phar <path>

#Options
\--coverage <file>

	Generate code coverage report in XML format.

\--output <file>

	Generate report in XML format.

#Config script file

##test/testman.conf.php

	[sample]
		<?php
		return array(
			'urls'=>\ebi\Dt::get_urls(),
			'output'=>dirname(__DIR__).'/work/result.xml',
			'ssl_verify'=>false,
		);

#Fixture of the test
	##test/testman.fixture.php
	
	[sample]
	<?php
	\ebi\Dt::setup();

#Libs of the test
	##test/testman.lib/**


#Function

	/**
	 * 失敗とする
	 * @param string $msg 失敗時メッセージ
	 * @throws \testman\AssertFailure
	 */
	failure($msg='failure')

	/**
	 *　等しい
	 * @param mixed $expectation 期待値
	 * @param mixed $result 実行結果
	 * @param string $msg 失敗時メッセージ
	 */
	eq($expectation,$result,$msg='failure equals')

	/**
	 * 等しくない
	 * @param mixed $expectation 期待値
	 * @param mixed $result 実行結果
	 * @param string $msg 失敗時メッセージ
	 */
	neq($expectation,$result,$msg='failure not equals')
	
	/**
	 *　文字列中に指定の文字列が存在する
	 * @param string|array $keyword
	 * @param string $src
	 * @param string $msg 失敗時メッセージ
	 */
	meq($keyword,$src,$msg='failure match')

	/**
	 * 文字列中に指定の文字列が存在しない
	 * @param string $keyword
	 * @param string $src
	 */
	mneq($keyword,$src,$msg='failure not match')

	/**
	 * mapに定義されたurlをフォーマットして返す
	 * @param string $map_name
	 * @throws \RuntimeException
	 * @return string
	 */
	test_map_url($map_name)


#Special script file
	__before__.php
	__after__.php


#Util

	\testman\Browser::
		/**
		 * リクエスト時のクエリ
		 * @param string $key
		 * @param string $value
		 */
		vars($key,$value=null)
		
		/**
		 * リクエスト時の添付ファイル
		 * @param string $key
		 * @param string $filepath
		 */
		file_vars($key,$filepath)
		
		/**
		 * 結果ヘッダの取得
		 * @return string
		 */
		head()
	
		/**
		 * 結果ボディーの取得
		 * @return string
		 */
		body()

		/**
		 * bodyを解析しXMLオブジェクトとして返す
		 * @return \testman\Xml
		 */
		xml()
		
		/**
		 * 最終実行URL
		 * @return string
		 */
		url()
		
		/**
		 * 最終HTTPステータス
		 * @return integer
		 */
		status()
		
		/**
		 * HEADでリクエスト
		 * @param string $url
		 * @return self
		 */
		do_head($url)
		
		/**
		 * PUTでリクエスト
		 * @param string $url
		 * @return self
		 */
		do_put($url)
		
		/**
		 * DELETEでリクエスト
		 * @param string $url
		 * @return self
		 */
		do_delete($url)
		
		/**
		 * GETでリクエスト
		 * @param string $url
		 * @return self
		 */
		do_get($url)
		
		/**
		 * POSTでリクエスト
		 * @param string $url
		 * @return self
		 */
		do_post($url)
		
		/**
		 * GETでリクエストしてダウンロード
		 * @param string $url
		 * @param string $download_path 保存パス
		 * @return self
		 */
		do_download($url,$download_path)
		
		/**
		 * POSTでリクエストしてダウンロード
		 * @param string $url
		 * @param string $download_path 保存パス
		 * @return self
		 */
		do_post_download($url,$download_path)


	\testman\Xml->
		/**
		 * 値を設定、取得する
		 * @param mixed
		 * @param boolean
		 * @return string
		 */
		value()
		
		/**
		 * 値を追加する
		 * ２つ目のパラメータがあるとアトリビュートの追加となる
		 * @param mixed $arg
		 */
		add($arg)
		
		/**
		 * アトリビュートを取得する
		 * @param string $n 取得するアトリビュート名
		 * @param string $d アトリビュートが存在しない場合の代替値
		 * @return string
		 */
		in_attr($n,$d=null)
		
		/**
		 * アトリビュートから削除する
		 * パラメータが一つも無ければ全件削除
		 */
		rm_attr()
		
		/**
		 * アトリビュートがあるか
		 * @param string $name
		 * @return boolean
		 */
		is_attr($name)
		
		/**
		 * アトリビュートを設定
		 * @return self $this
		 */
		attr($key,$value)
		
		/**
		 * 値の無いアトリビュートを設定
		 * @param string $v
		 */
		plain_attr($v)
		
		/**
		 * XML文字列を返す
		 */
		get($encoding=null)
		
		/**
		 * 検索する
		 * @param string $name
		 * @param integer $offset
		 * @param integer $length
		 * @return \testman\XmlIterator
		 */
		find($name,$offset=0,$length=0)
		
		/**
		 * １件取得する
		 * @param string $name
		 * @param integer $offset
		 * @throws \testman\NotFoundException
		 * @return \testman\Xml
		 */
		find_get($name,$offset=0)
		
	
	\tesmtan\Xml::
		/**
		 * 匿名タグとしてインスタンス生成
		 * @param string $value
		 * @return \testman\Xml
		 */
		anonymous($value)
		
		/**
		 * タグの検出
		 * @param string $plain
		 * @param string $name
		 * @throws \testman\NotFoundException
		 * @return \testman\Xml
		 */
		extract($plain,$name=null)


