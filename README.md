# testman

PHP 7.4+ 向けの軽量テスティングフレームワークです。シンプルなアサーション関数、setup/teardown による変数管理、HTTP テスト用ブラウザ、XML/JSON パーサを備えています。

## インストール

### PHAR を使う場合

```bash
# ビルド済みの testman.phar をプロジェクトに配置
cp testman.phar /path/to/your/project/
```

### ソースからビルド

```bash
git clone <repository-url>
cd testman
./make.sh  # testman.phar を生成
```

## プロジェクト構成

テストを実行するプロジェクトは以下の構成を想定しています。

```
your-project/
├── test/                       # テストディレクトリ（デフォルト）
│   ├── testman.settings.php    # テスト設定ファイル（任意）
│   ├── testman.fixture.php     # フィクスチャ初期化（任意）
│   ├── testman.lib/            # テスト用ライブラリ（任意）
│   ├── testman.resources/      # テスト用リソースファイル（任意）
│   └── case/                   # テストケースファイル
│       ├── __setup__.php       # セットアップ（任意）
│       ├── __teardown__.php    # ティアダウン（任意）
│       ├── sample_test.php     # テストファイル
│       └── sub/
│           ├── __setup__.php   # サブディレクトリ用セットアップ
│           └── sub_test.php
├── testman.phar
└── vendor/autoload.php         # Composer autoload（あれば自動読込）
```

## 基本的な使い方

### テストの実行

```bash
# test/ ディレクトリ配下のテストを実行
php testman.phar

# 特定のディレクトリのテストを実行
php testman.phar test/case/xml/

# 特定のファイルのテストを実行
php testman.phar test/case/eq.php

# 並列実行（CPUコア数を自動検出）
php testman.phar -p

# ワーカー数を指定して並列実行
php testman.phar -p 4
php testman.phar --parallel 4
```

### コマンドオプション

| オプション | 説明 |
|---|---|
| `--help` | ヘルプを表示 |
| `--list [keyword]`, `-l [keyword]` | テストファイル一覧を表示（キーワードで絞り込み可） |
| `--info`, `-i` | setup/teardown の情報を表示 |
| `-I` | setup/teardown の詳細情報（ファイルパス付き）を表示 |
| `-p [N]`, `--parallel [N]` | N個のワーカーで並列実行（省略時はCPUコア数） |

### 終了コード

| コード | 意味 |
|---|---|
| `0` | 全テスト成功 |
| `1` | テスト失敗またはエラー |
| `130` | 中断（SIGINT） |

## テストの書き方

テストファイルは通常の PHP ファイルです。アサーション関数を使って検証を行います。

### アサーション関数

#### `eq($expectation, $result, $msg)` - 等しいことを検証

```php
<?php
// 基本的な等値比較
eq(1, 1);
eq('hello', 'hello');
eq(true, true);

// オブジェクトの比較
$obj1 = (object)['a' => 1, 'b' => 2];
$obj2 = (object)['a' => 1, 'b' => 2];
eq($obj1, $obj2);

// 引数1つの場合は true との比較
eq(is_string('hello'));  // eq(true, is_string('hello')) と同じ

// カスタムメッセージ
eq(200, $status, 'ステータスコードが200であること');
```

#### `neq($expectation, $result, $msg)` - 等しくないことを検証

```php
<?php
neq(1, 2);
neq('hello', 'world');
```

#### `meq($keyword, $result, $msg)` - 文字列に含まれることを検証

```php
<?php
meq('hello', 'hello world');       // 'hello world' に 'hello' が含まれる
meq('<title>', $html_string);      // HTML に <title> タグが含まれる
```

#### `mneq($keyword, $result, $msg)` - 文字列に含まれないことを検証

```php
<?php
mneq('error', $response_body);     // レスポンスに 'error' が含まれない
```

#### `fail($msg)` - テストを明示的に失敗させる

```php
<?php
if($something_unexpected){
    fail('想定外の状態です');
}
```

## Setup / Teardown

`__setup__.php` と `__teardown__.php` を使ってテストの前後処理を定義できます。

### 基本的な使い方

```php
<?php
// test/case/__setup__.php

/**
 * テスト用データの初期化
 * @export string $data_file データファイルパス
 * @export string $var_a テスト用変数A
 */
$data_file = getcwd().'/testdata.dat';
file_put_contents($data_file, 'test content');

$var_a = 'hello';
```

```php
<?php
// test/case/__teardown__.php
unlink($data_file);
```

```php
<?php
// test/case/sample.php

// $data_file と $var_a は __setup__.php で定義された値が利用可能
eq('test content', file_get_contents($data_file));
eq('hello', $var_a);
```

### `@export` アノテーション

setup ファイルの PHPDoc で `@export` を使って変数を宣言します。宣言された変数はテストファイルに自動的に渡されます。

```
@export <型> $<変数名> <説明>
```

サポートされる型:

| 型 | 説明 |
|---|---|
| `string` | 文字列 |
| `int`, `integer` | 整数 |
| `float` | 浮動小数点 |
| `bool`, `boolean` | 真偽値 |
| `ClassName` | クラスのインスタンス |
| `string[]`, `int[]` 等 | 配列（各要素の型チェック付き） |

型を省略した場合は `string` として扱われます。

### ディレクトリ階層と変数の継承

setup ファイルはディレクトリ階層を辿って上位から順に実行されます。子ディレクトリの setup で同名の変数を再定義すると上書きされます。

```
test/case/__setup__.php          # 最初に実行: $var_a = 'XXX'
test/case/sub1/__setup__.php     # 次に実行: $var_a = 'AAA' に上書き、$var_b を追加
test/case/sub1/test.php          # $var_a = 'AAA', $var_b = 'BBB' が利用可能
```

## 設定ファイル

### testman.settings.php

テスト全体の設定を行います。テスト実行時に自動的に読み込まれます。

```php
<?php
// test/testman.settings.php

\testman\Conf::set([
    // URLの短縮名を定義
    'urls' => [
        'select' => 'http://localhost:8000/entry_%s.php',
        'cookie' => 'http://localhost:8000/cookie.php',
    ],
    // URLの書き換えルール（正規表現）
    'url_rewrite' => [
        '/http:\/\/localhost:8000\/cookie2\.php/' => 'cookie',
    ],
    // SSL証明書の検証を無効化（開発環境用）
    'ssl-verify' => false,
    // デフォルトの Accept ヘッダ
    'accept' => 'application/json',
]);
```

### testman.fixture.php

テスト実行前に一度だけ呼ばれるフィクスチャ初期化ファイルです。データベースの初期化やテストデータの作成に利用します。

```php
<?php
// test/testman.fixture.php

// テスト用データベースの初期化など
file_put_contents(getcwd().'/fixture_data.dat', 'testdata');
```

### testman.lib/

テスト用のカスタムライブラリを配置するディレクトリです。`test\` 名前空間で自動的にオートロードされます。

```php
<?php
// test/testman.lib/Helper.php
namespace test;

class Helper{
    public static function create_user(): array{
        return ['name' => 'test', 'email' => 'test@example.com'];
    }
}
```

```php
<?php
// テストファイルから利用
$user = \test\Helper::create_user();
eq('test', $user['name']);
```

### testman.resources/

テスト用のリソースファイルを配置するディレクトリです。`\testman\Resource::path()` でアクセスします。

```php
<?php
$path = \testman\Resource::path('sample.json');
$data = json_decode(file_get_contents($path), true);
```

## HTTP テスト（Browser）

`b()` 関数で Browser インスタンスを生成し、HTTP リクエストのテストを行えます。

### GET / POST リクエスト

```php
<?php
$b = b();

// GET リクエスト
$b->do_get('http://localhost:8000/api/users');
eq(200, $b->status());
meq('users', $b->body());

// POST リクエスト
$b->vars('name', 'test_user');
$b->vars('email', 'test@example.com');
$b->do_post('http://localhost:8000/api/users');
eq(201, $b->status());
```

### URL短縮名の利用

`testman.settings.php` で定義した URL を利用できます。

```php
<?php
// 'select' => 'http://localhost:8000/entry_%s.php' と定義されている場合
$b = b();
$b->do_get(['select', 1]);  // http://localhost:8000/entry_1.php にアクセス
```

### JSON リクエスト / レスポンス

```php
<?php
$b = b();
$b->vars('name', 'test');
$b->vars('value', 123);
$b->do_json('http://localhost:8000/api/data');  // Content-Type: application/json でPOST

// JSON レスポンスの検証
eq('test', $b->json('name'));
eq(123, $b->json('data/value'));  // ネストしたパスでアクセス
```

### XML レスポンス

```php
<?php
$b = b();
$b->do_get('http://localhost:8000/api/data.xml');

// XML レスポンスの検証
$xml = $b->xml();
eq('root', $xml->name());

// 要素の検索
foreach($xml->find('item') as $item){
    meq('value', $item->value());
}
```

### 認証

```php
<?php
$b = b();

// Basic 認証
$b->basic('username', 'password');
$b->do_get('http://localhost:8000/api/protected');

// Bearer トークン
$b = b();
$b->bearer_token('your-api-token');
$b->do_get('http://localhost:8000/api/protected');
```

### カスタムヘッダ

```php
<?php
$b = b();
$b->header('X-Custom-Header', 'value');
$b->header('Accept', 'application/xml');
$b->do_get('http://localhost:8000/api/data');
```

### ファイルアップロード

```php
<?php
$b = b();
$b->vars('title', 'My File');
$b->file_vars('upload', '/path/to/file.pdf');
$b->do_post('http://localhost:8000/api/upload');
```

### ファイルダウンロード

```php
<?php
$b = b();
$b->do_download('http://localhost:8000/files/report.pdf', '/tmp/report.pdf');
eq(200, $b->status());

// POST でダウンロード
$b->vars('id', 123);
$b->do_post_download('http://localhost:8000/files/export', '/tmp/export.csv');
```

### その他のメソッド

```php
<?php
$b = b();

// PUT / DELETE / HEAD リクエスト
$b->do_put('http://localhost:8000/api/users/1');
$b->do_delete('http://localhost:8000/api/users/1');
$b->do_head('http://localhost:8000/api/users');

// RAW ボディ送信
$b->do_raw('http://localhost:8000/api/data', '<xml>raw content</xml>');

// タイムアウト設定（秒）
$b->timeout(60);

// リダイレクト上限設定
$b->redirect_max(5);

// ユーザエージェント設定
$b->agent('CustomBot/1.0');

// レスポンスヘッダの取得（連想配列）
$headers = $b->explode_head();

// Cookie の取得
$cookies = $b->cookies();

// リクエストの記録
Browser::start_record();
$b->do_get('http://localhost:8000/api/data');
$requests = Browser::stop_record();

// デバッグモード（Accept: application/debug を自動設定）
Browser::debug();
```

## XML パーサ

### XML の生成

```php
<?php
$xml = new \testman\Xml('user', 'John');
echo $xml->get();  // <user>John</user>

// アトリビュート付き
$xml = new \testman\Xml('user');
$xml->attr('id', '1');
$xml->attr('active', true);
$xml->value('John');
echo $xml->get();  // <user id="1" active="true">John</user>

// 子要素を含む
$xml = new \testman\Xml('users');
$child = new \testman\Xml('user', 'John');
$xml->add($child);
echo $xml->get();  // <users><user>John</user></users>

// エンコーディング指定
echo $xml->get('UTF-8');  // <?xml version="1.0" encoding="UTF-8" ?>...

// 整形出力
echo $xml->get(null, true);
```

### XML の解析

```php
<?php
$html = '<html><body><div class="content"><p>Hello</p></div></body></html>';

// タグ名で抽出
$xml = \testman\Xml::extract($html, 'div');
eq('content', $xml->in_attr('class'));

// パスで検索
$xml = \testman\Xml::extract($html, 'body');
$p = $xml->find_get('div/p');
eq('Hello', $p->value());

// 複数要素の検索
$src = '<root><item>A</item><item>B</item><item>C</item></root>';
$xml = \testman\Xml::extract($src, 'root');

foreach($xml->find('item') as $item){
    // $item->value() で A, B, C を順に取得
}

// 要素数の取得
$count = $xml->find_count('item');

// 1件取得（offset指定）
$second = $xml->find_get('item', 1);
eq('B', $second->value());

// 子要素の展開
$children = $xml->children();
```

## JSON パーサ

```php
<?php
$json_str = '{"user":{"name":"John","age":30},"tags":["php","test"]}';
$json = new \testman\Json($json_str);

// パスで値を取得
eq('John', $json->find('user/name'));
eq(30, $json->find('user/age'));
eq('php', $json->find('tags/0'));

// 全体を取得
$all = $json->find();

// エンコード / デコード
$encoded = \testman\Json::encode(['key' => 'value']);
$decoded = \testman\Json::decode('{"key":"value"}');

// 整形エンコード
$formatted = \testman\Json::encode(['key' => 'value'], true);
```

## テストファイルの命名規則

- `_` または `.` で始まるファイルはテスト対象外
- `testman.` で始まるファイルは設定ファイルとして扱われテスト対象外
- それ以外の `.php` ファイルがテスト対象

## テスト結果の見方

テスト実行時にはプログレスバーとともにリアルタイムで結果が表示されます。

```
testman 3.0.1 (PHP 8.2.0)

[15/15] ██████████████████████████████ 100% 15 ✓

Results:
================================================================================
15 tests: 15 passed, 0 failed, 0 errors (2024-01-01 12:00:00 / 0.123s / 2.50MB)
```

失敗時はアサーション失敗箇所の期待値と実際の値が表示されます。

```
Results:

 test/case/sample.php
  [5]: failure equals
   ---------------------------------------------------------- expect ---
   string(5) "hello"
   ---------------------------------------------------------- result ---
   string(5) "world"

================================================================================
10 tests: 9 passed, 1 failed, 0 errors (2024-01-01 12:00:00 / 0.456s / 3.00MB)
```

## Conf 設定項目一覧

| 設定キー | 型 | 説明 |
|---|---|---|
| `urls` | `array` | URL短縮名の定義 |
| `url_rewrite` | `array` | URL書き換えルール（正規表現 => 置換先） |
| `ssl-verify` | `bool` | SSL証明書検証の有無（デフォルト: `true`） |
| `accept` | `string` | デフォルトの Accept ヘッダ |
| `browser_has_error_func` | `callable` | Browser のエラー判定カスタム関数 |
| `browser_find_func` | `callable` | Browser の要素検索カスタム関数 |
| `log_debug_callback` | `callable` | デバッグログのコールバック関数 |

## 動作要件

- PHP 7.4 以上
- cURL 拡張（HTTP テスト利用時）
- pcntl 拡張（並列実行利用時、任意）
- mbstring 拡張（推奨）
