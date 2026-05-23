# php-unearther

php-uneartherは、レガシーAPIアプリケーションのランタイム動作を観測し、移行作業向けの振る舞いレポートを生成するための実験的なPHPライブラリです。

APMの代替ではありません。次のような疑問に答えることを目的としています：

- このエンドポイントはどのSQLを実行しているか？
- どのテーブルを読み書きしているか？
- 実際のトラフィックで観測される実行パターンは何か？
- どの外部HTTP呼び出しが行われているか？
- 新しい実装はレガシーAPIの観測された振る舞いにどれだけ近いか？

最初の実装ターゲットはPHP 7.3上のCodeIgniter3です。コアスキーマは後でLaravel 11アダプターでも再利用できるよう設計されています。

コアパッケージはPHP 7.3以降をサポートしており、PHP 8専用の構文を避けることで、古いCodeIgniter3アプリケーションにインストールしながら、PHP 8プロジェクトでも同じレポートツールを利用できます。

## 開発の動機

レガシーAPIの移行は、不完全または古い仕様から始まることが多くあります。ソースコードを読んでも、本番環境で実際に使われている分岐、各エンドポイントで現れるSQLパターン、関連する外部サービスなどが常に明らかになるとは限りません。

php-uneartherは、サンプリングされたリクエストから観測された事実をキャプチャし、移行作業で役立つレポートに変換することを目的としています。レポートは意図的に振る舞い中心の設計になっており、エンドポイントの形状、SQL/テーブルのフロー、外部HTTP呼び出し、繰り返される実行パターンを示します。

## 現状

このパッケージは初期プロトタイプです。

現在の実装範囲：

- CodeIgniter3向けインストゥルメンテーション
- JSONLによる観測ログ
- SQL/テーブルの観測
- Guzzleベースの外部HTTP観測
- MarkdownおよびJSON形式の振る舞いレポート

初期スコープに含まれないもの：

- セッションdiffトラッキング
- デフォルトでの生リクエスト/レスポンスキャプチャ
- マスキングポリシーエンジン
- curlインターセプト
- PHPエクステンションフック
- DBのbefore/afterスナップショット
- 完全なOpenAPI生成

## インストール

Packagistへの公開前は、ComposerのVCSリポジトリとしてGitHubからインストールしてください。

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/coffee-r/php-unearther"
    }
  ],
  "require": {
    "coffee-r/php-unearther": "dev-main"
  }
}
```

その後、以下を実行：

```bash
composer update coffee-r/php-unearther
```

## 観測モデル

php-uneartherは、サンプリングされたリクエストごとに1つのJSONオブジェクトを書き出します。

各観測レコードには`schema_version`フィールドが含まれます。現在のスキーマバージョンは`1`です。

観測レコードに含まれる情報：

- HTTPメソッド、生の`path`、正規化された`path_pattern`、`route`、ステータス、`response_kind`（`json` / `html` / `other`）、`query_shape`、`query_raw`（デフォルトnull）、`request_shape`、`request_raw`（デフォルトnull）、`response_shape`
- アダプターが提供できる場合のコントローラーまたはルートメタデータ
- SQLの操作種別、`tables`、`statement_normalized`（リテラルを`{parameter}`に置換）、`statement_text`（生SQL、オプトイン；それ以外は`null`）、`statement_hash`（`statement_normalized`のsha256）、`bind_shape`、`caller`
- Guzzleミドルウェア経由で記録された外部HTTP呼び出し（ホスト、メソッド、パス、ステータス、caller）
- アダプターまたはラッパーが記録したエラー

可能な場合、値は「形状（shape）」として表現されます。例えばリクエストフィールドは生の値ではなく、`string`、`number`、`boolean`、`array`、またはネストされた構造として記録されます。

実行時間/レイテンシは意図的に記録していません。php-uneartherはエンドポイントが観測可能な動作を明らかにするためのもので、速度の測定用ではありません。

観測スキーマには将来のコントローラー/モデル/ライブラリの呼び出しトレースイベント用として`calls`配列が含まれています。現在のCodeIgniter3アダプターはこれを自動的に埋めません；このプロトタイプでサポートされているコールサイトのシグナルはSQL callerメタデータとGuzzle外部HTTPイベントです。

## 本番データの安全性

本番環境や本番相当の環境でphp-uneartherを有効にする際は注意が必要です。生のリクエスト・レスポンス値を保存しない場合でも、観測ログには機密情報が含まれる可能性があります。

有効化前に以下を確認してください：

- エンドポイントパス、クエリキー、リクエストキー、レスポンスキー、SQLテキスト、bind shape、テーブル名、callerパス、外部ホスト名などに個人情報・認証情報・テナント識別子・内部システム詳細が含まれていないか
- アプリケーションがバインドパラメーターを使わずSQL文字列を構築している場合、SQLステートメントにリテラル値が含まれていないか
- ログファイルが適切なファイルシステム権限を持つ制限されたパスに書き込まれているか
- ログの保持・バックアップ・転送・削除ポリシーがアプリケーションの機密度と一致しているか
- サンプルレートが本番トラフィックに対して十分に低く、迅速に無効化できるか
- ログから生成されたレポートが生のJSONLログと同等の注意を持って扱われているか

初期プロトタイプにはマスキングポリシーエンジンは含まれていません。観測フィールドにシークレット、トークン、メールアドレス、氏名、住所、電話番号、決済識別子、顧客識別子が含まれる可能性がある場合は、アプリケーション側でフィルタリングを追加するか、キャプチャ範囲が明確になるまでphp-uneartherを無効のままにしてください。

## 設定

アダプターによって設定の読み込み方は異なりますが、パッケージは同じオプションに正規化します。

```php
array(
    'enabled' => true,
    'service' => 'legacy-api',
    'framework' => 'codeigniter3',
    'failure_mode' => 'throw',
    'sample_rate' => 0.01,
    'sink' => array(
        'type' => 'jsonl',
        'path' => APPPATH . 'logs/unearther-{date}.jsonl',
        'date_format' => 'Y-m-d',
    ),
    'codeigniter3' => array(
        'sql_capture' => 'query_history',
    ),
    'sql' => array(
        'capture_text' => false,
    ),
    'http' => array(
        'capture_json_request_shape' => true,
        'capture_json_response_shape' => false,
        'max_body_bytes' => 65536,
        'endpoint_patterns' => array(
            array('method' => 'GET', 'path' => '/api/users/{id}', 'name' => 'users.show'),
            array('method' => 'POST', 'path' => '/api/cart/add', 'name' => 'cart.add'),
        ),
    ),
)
```

CodeIgniter3の場合、このarrayをhookのparamsとして渡してください。将来のLaravelアダプターでは`config/unearther.php`から同じキーを利用できます。

`codeigniter3.sql_capture`には`query_history`、`observed_db`、`none`を指定できます。古い`codeigniter3.capture_query_history`キーも互換性のエイリアスとして引き続き受け付けます。

`sql.capture_text`はデフォルトでオフです。SQLイベントは常に`statement_normalized`（リテラルを`{parameter}`に置換）と`statement_hash`を出力します。`capture_text`を`true`にすると生SQLが`statement_text`にも書き込まれます；それ以外は`null`です。テキストキャプチャの有効化は移行分析には有用ですが、アプリケーションがリテラルでSQLを構築している場合に機密値を露出する可能性があります。

`failure_mode`は、php-unearther自体がリクエスト観測中に失敗した場合の動作を制御します。デフォルトは`throw`で、インストールや設定の問題がロールアウト中に可視化されます。本番環境で観測失敗時もアプリケーションを継続させたい場合は`log`に設定してください。`log`モードでは、CodeIgniter3は利用可能な場合`log_message('error', ...)`を使用し、それ以外は`error_log()`にフォールバックします。

`http.endpoint_patterns`はオプションです。リクエストが`/api/users/{id}`のようなLaravelスタイルのパスパターンにマッチした場合、観測の`path_pattern`がその正規化された形式に設定され、レポートがそれでグループ化されます。php-uneartherはパスパラメーターを自動推定しません；設定されていないパスは`path_pattern`が生のリクエストパスと同じ値になります。

## CodeIgniter3での使い方

現在のCodeIgniter3アダプターは、PHP-FPM、CGI、Apache mod_phpなどの従来型PHPリクエストライフサイクルを前提としています。Swoole、RoadRunner、ReactPHPなどの長期稼働ワーカーランタイムは、このプロトタイプではサポートされていません（フックがリクエスト観測状態をプロセスローカルのstaticフィールドに保持するため）。

CodeIgniter3はhook定義内でnamespaceつきComposerクラスをネイティブに前提としていません。最も安全なセットアップは、アプリケーション内に小さなブリッジhookを作成し、そこからphp-uneartherを呼び出す方法です。

`application/hooks/UneartherHook.php`を作成してください：

```php
<?php

use CoffeeR\Unearther\Adapter\CodeIgniter3\Hook;

class UneartherHook
{
    private $hook;

    public function __construct()
    {
        require_once FCPATH . 'vendor/autoload.php';
        $this->hook = new Hook();
    }

    public function start($config = array())
    {
        $this->hook->start($config);
    }

    public function finish($config = array())
    {
        $this->hook->finish($config);
    }
}
```

次に`application/config/hooks.php`でブリッジを登録します：

```php
$hook['pre_system'][] = array(
    'class' => 'UneartherHook',
    'function' => 'start',
    'filename' => 'UneartherHook.php',
    'filepath' => 'hooks',
    'params' => array(array(
        'service' => 'legacy-api',
        'sample_rate' => 0.01,
        'sink' => array(
            'path' => APPPATH . 'logs/unearther-{date}.jsonl',
        ),
    )),
);

$hook['post_system'][] = array(
    'class' => 'UneartherHook',
    'function' => 'finish',
    'filename' => 'UneartherHook.php',
    'filepath' => 'hooks',
    'params' => array(),
);
```

### CodeIgniter3のSQLキャプチャ

デフォルトでは、CodeIgniter3フックは`sql_capture => query_history`を使用し、サンプリングされたリクエストの終了時に`$CI->db->queries`と`$CI->db->query_times`を読み取ります。これを最初のキャプチャ戦略として採用しているのは、CodeIgniterの`save_queries`設定が有効であれば、`where()`、`get()`、`insert()`、直接の`query()`呼び出しなどのQuery Builderの呼び出しで生成されるSQLを観測できるためです。

この戦略にはトレードオフがあります：

- CodeIgniterのクエリ履歴が利用可能であることに依存する
- 正確なcallerのファイル/行情報が得られない
- 1リクエストで多数のクエリが実行される場合、メモリ使用量が増加する可能性がある

SQLキャプチャを無効化することもできます：

```php
'codeigniter3' => array(
    'sql_capture' => 'none',
)
```

制御できるアプリケーションのブートストラップポイント向けに、実験的なDBラッパーも利用可能です：

```php
use CoffeeR\Unearther\Adapter\CodeIgniter3\Hook;
use CoffeeR\Unearther\Adapter\CodeIgniter3\ObservedDb;
use CoffeeR\Unearther\Sql\SqlAnalyzer;

$CI =& get_instance();
$CI->db = new ObservedDb($CI->db, Hook::collector());
```

`ObservedDb`使用時にSQLテキストも含めたい場合は、テキストキャプチャを有効にしたアナライザーを渡します：

```php
$CI->db = new ObservedDb($CI->db, Hook::collector(), new SqlAnalyzer(true));
```

`ObservedDb`を使用する場合は、フックがCodeIgniterのクエリ履歴を二重に記録しないよう`sql_capture => observed_db`を設定してください：

```php
'codeigniter3' => array(
    'sql_capture' => 'observed_db',
)
```

このラッパーは`query()`を通じた呼び出しを記録しますが、Query Builderを完全にインターセプトする戦略ではありません。多くのCI3アプリケーションでは、クエリ履歴キャプチャの方がより現実的なベースラインです。

### HTTPシェイプキャプチャ

サンプリングされたリクエストに対して、CodeIgniter3フックは`query_shape`と`request_shape`を記録します。リクエストのContent-Typeが`application/json`または構造化された`+json`タイプの場合、php-uneartherはボディをデコードしてJSONの形状を記録します。ボディが無効なJSON、サイズ超過、またはJSON以外の場合は`$_POST`の形状にフォールバックします。

JSONリクエスト形状のキャプチャは`php://input`を読み取ります。サポートされているPHPランタイムでは通常再読み取り可能ですが、フックが実行される前にアプリケーションのブートストラップコードが生の入力を消費または置き換えた場合、php-uneartherはJSONボディを読み取れず`$_POST`にフォールバックします。

レスポンスボディの形状キャプチャはデフォルトでオフです。レスポンスの内容を確認してから有効化してください：

```php
'http' => array(
    'capture_json_response_shape' => true,
    'max_body_bytes' => 65536,
)
```

有効化すると、CodeIgniter3フックはJSONコンテンツタイプを持つサンプリングされたレスポンスに対して`$CI->output->get_output()`を読み取り、レスポンスの形状のみを記録します。

## トレースID

各サンプリングされたリクエストは、観測開始時に生成されたトレースIDを受け取ります。IDにはUTCタイムスタンプのプレフィックスと128ビットのランダムなサフィックスが含まれます：

```text
20260601T101122-3f4e3f8b9c0d4d67a1b2c3d4e5f60718
```

これにより、開始時刻でのソートが可能になりながら、通常のサンプリング量では衝突が実質的に発生しません。

## ログローテーション

JOSNLシンクはパスの`{date}`プレースホルダーによる日次ファイルローテーションをサポートしています：

```php
'sink_path' => APPPATH . 'logs/unearther-{date}.jsonl'
```

これにより以下のようなファイルが書き出されます：

```text
unearther-2026-06-01.jsonl
unearther-2026-06-02.jsonl
```

パスに`{date}`が含まれない場合、php-uneartherはそのパスにそのまま書き込みます。

## Guzzleでの使い方

Guzzleのハンドラースタックにミドルウェアをアタッチします：

```php
use CoffeeR\Unearther\Guzzle\UneartherMiddleware;
use GuzzleHttp\HandlerStack;

$stack = HandlerStack::create();
$stack->push(UneartherMiddleware::create($collector));
```

そのクライアントを通じた外部HTTP呼び出しが現在のトレースに追加されます。

## レポート

Markdown形式の振る舞いレポートを生成する：

```bash
vendor/bin/unearth report application/logs/unearther.jsonl --format md
```

機械可読な集計レポートを生成する：

```bash
vendor/bin/unearth report application/logs/unearther.jsonl --format json
```

レポートはエンドポイントと観測された実行パターンでトレースをグループ化します。パターンは現在、SQLの操作/テーブル/ハッシュのフローとGuzzle外部HTTP呼び出しに基づいています。パターンの粒度はこのプロトタイプでは固定されており、設定できません。

キャプチャ時に`http.endpoint_patterns`が設定されている場合、レポートは記録された`path_pattern`でグループ化されます。レポートにはエンドポイントレベルのエラー数と、トレースの`errors`からグループ化されたエラーサマリーも含まれます。

Markdownレポートの各パターンは`#### Representative Case`ブロックで終わり、そのパターンで最初に観測されたトレースの名前、`path (canonical)`と`path (concrete)`、`statement_normalized`形式のSQLリスト、および外部API呼び出しが示されます。キャプチャ時に`sql.capture_text`が有効だった場合、正規化された形式の横に具体的なSQLも表示されます。

集計レポートとMarkdownレポートは、キャプチャ時に`sql.capture_text`が有効でなければ生SQLテキストをレンダリングしません。SQLフローテーブルには常に`statement_hash`と`statement_normalized`が含まれます。

CLIは現在レポート生成のみをサポートしています。`compare`コマンドや`--from` / `--to`による日付フィルタリングは含まれていません。

パターンの例：

```text
SELECT M_SHOHIN -> INSERT T_CART
```

## 開発

```bash
composer install
vendor/bin/phpunit
php bin/unearth report tests/Fixtures/jsonl/cart_add.jsonl --format md
```

テストスイートはフィクスチャ駆動で、決定論的な動作に焦点を当てています：

- shape抽出
- JSONリクエスト・レスポンスのshape抽出
- SQLの操作/テーブル抽出
- エンドポイントパターンの正規化
- CodeIgniter3フックのライフサイクル動作
- JSONLの書き込み
- CLIの警告動作
- エンドポイントの集計
- エラーの集計
- 実行パターンのグループ化
- Markdownのレンダリング

## OpenAI Codexで構築

このプロトタイプはOpenAI Codexの助けを借りて設計・実装されました。

## ライセンス

MIT