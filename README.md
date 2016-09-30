# testman

(PHP 5 >= 5.5.0)

![ebi](teacher_saiten_man.png)

[![download](button_download2.png)](https://git.io/testman.phar)


## Quick test

	$ php test/testman.phar <path>


## Options
\--coverage <file>

	Generate code coverage report in XML format.

\--output <file>

	Generate report in XML format.

\--list <keyword>

	List test files.

\--info

	Info setup[s].

\--init

	Create init files



## Config script file

### test/testman.settings.php

	[sample]
		<?php
			\testman\Conf::set('urls',\ebi\Dt::get_urls());
			\testman\Conf::set('output',dirname(__DIR__).'/work/result.xml');
			\testman\Conf::set('ssl-verify',false);

## Fixture of the test
	##test/testman.fixture.php
	
	[sample]
	<?php
	\ebi\Dt::setup();

## Class Library of the test
	##test/testman.lib/Abc.php
	
	namespace is `test` only
	
	[Abc.php]
		<?php
			namespace test;
			class Abc{
			}

## Function

	/**
	 * 失敗とする
	 * @param string $msg 失敗時メッセージ
	 * @throws \testman\AssertFailure
	 */
	fail($msg='failure')

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
	url($map_name)

	/**
	 * \testman\Browser()
	 */
	b()

## Special script file
	__setup__.php
	__teardown__.php


## Util

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
		 * @param string $name node名
		 * @return \testman\Xml
		 */
		xml($name=null)
		
		/**
		 * bodyをJsonとして解析し連想配列を返す
		 * @param string $name キー名
		 * @return \testman\Xml
		 */
		json($name=null)		
		
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
		 * アトリビュートを取得する
		 * @param string $n 取得するアトリビュート名
		 * @param string $d アトリビュートが存在しない場合の代替値
		 * @return string
		 */
		in_attr($n,$d=null)
		
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
	