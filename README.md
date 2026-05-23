# php-unearther

レガシーPHP Appの実行時の振る舞いを掘り起こし、仕様調査と移行調査に使うための観測ツール。

php-uneartherは、レガシーPHPアプリケーションの実行時の振る舞いを **言語非依存のJSONLログ** として記録し、人間や生成AIが安全に分析できる形へ変換するPHPライブラリを目指します。

最初の実装対象は、PHP 7.3で動くCodeIgniter3製のWebアプリケーションです。JSONを返すAPIだけでなく、CodeIgniter3でよくある「controller → model → DB、最後にviewでHTMLをレンダリングするMVCモノリス」も観測対象に含めます。

移行先としてLaravel 10以降を主に想定し、将来的にはC#など他言語も視野に入れます。ただし、v1で全adapterを作り込むのではなく、まずはどの言語からでも出せるJSONL schemaを固めます。PHP→Laravel→C#のような移行先でも同じformatのログが出せれば、developerや生成AIが「同じ動きか」を確認できます。

> ここでの「レガシー」は、PHP 7.x + CodeIgniter3 LTSなど **modern PHP stack 以前の世代** を指す技術的な区分です。開発品質・運用品質・コミュニティ活動の評価ではありません。CI3は現在もLTSとしてsecurity patchが提供されており、本ツールはそうしたシステムを移行する際の調査作業を支援することを目的としています。

このパッケージは初期プロトタイプです。

## なぜ作るか

レガシーWebAppの移行では、既存仕様が十分に文書化されていないことが多くあります。

ソースコードを読むだけでは、次のようなことが分かりづらいです。

* そのendpointには、実際にどんなrequestが来ているのか
* 本番ではどんなresponse shapeを返しているのか
* どのSQLが実行されているのか
* 同じendpointでも条件によってSQLの呼び出し構造が違うはず
* 外部APIを呼び出しているのか
* 生成AIに分析させたいが、raw値をそのまま渡すのは危険ではないか

php-uneartherは、これらを「実際に動いた事実」から確認できるようにします。

汎用APMの代替ではありません。主目的は仕様発掘と、移行先実装でも同じformatのログを出して照合できる土台を作ることです。高度な分析、要約、pattern名付け、比較reportは生成AIや外部ツールに委ねられるので、php-unearther本体は **収集・正規化・マスキング・export** に寄せます。

## 主目的

既存のレガシーPHP Webアプリケーション (API / MVCモノリスの両方) から新しい実装へ移行するときに、仕様調査にかかる時間と不確実性を減らします。

そのために、サンプリングされたHTTPリクエストごとに以下を同じ`trace_id`でつなげます。

* HTTP method
* HTTP URL
* HTTP input
* HTTP output (status, response kind, response shapeなど)
* SQL呼び出し
* 外部HTTP呼び出し

目指す状態は、developerがendpointを見たときに、次のようなことを確認できることです。

> このendpointは、こういう入力で使われ、こういう出力を返し、このテーブルを触り、このSQLパターンで分岐し、この外部APIを呼んでいる。

そしてもう一つの軸として、 **JSONLログのschemaを言語非依存に固める** ことを重視します。同じschemaのログを移行先(Laravel / C# など)でも出せるようにしておけば、移行確認は「同じschemaのログ同士を見比べる」だけで成立します。ログ生成側を言語ごとに揃えればよく、比較ツールは本packageの責務にしません。

### v1で責任を持つこと

* sampled requestから観測事実を集める
* shape、normalized値、hash/token化された値を出す
* raw値は明示的に有効化された場合だけ保存する
* 生成AIや外部ツールに渡すためのredacted exportを出す
* JSONL schemaを仕様書として明示し、fixtureや生成ログをschema検証できるようにする
* HTML responseでは、response bodyそのものではなく、view/template名とviewに渡された変数のshapeを可能な範囲で記録する
* 最低限のJSON / Markdown集計を出す

### v1で責任を持たないこと

* 生成AIによる分析そのもの
* 既存実装と新実装の自動比較
* HTML UI / HTML reportの作り込み
* Mermaid sequence diagramの自動生成
* 完全なcall graph

## Schema仕様と検証

「言語非依存のJSONL schema」を名乗る以上、JSONLの構造はコードを読まなくても分かる場所に置きます。

v1では、少なくとも以下を用意します。

* `docs/schema/observation-v1.schema.json`
  * 1 JSON lineあたりの構造を定義するJSON Schema
  * `schema_version: 1` の必須フィールド、HTTP / SQL / external HTTP / redaction / errorsなどの構造を定義する
  * raw系フィールドは `null` または実値を許容するが、redacted exportでは含まれないことを別途検証する
* `docs/schema/README.md`
  * 各フィールドの意味、normalized / tokenized / raw の使い分け、信頼度の注意点を書く
* PHPUnitでのschema検証
  * fixture JSONLの各行を `observation-v1.schema.json` に通す
  * collector / exporter が生成したsample logもschemaに通す
  * redacted exportに `statement_text`、`path_raw`、`query_raw`、`request_raw`、`bind_raw` などraw系フィールドが残っていないことを検証する

JSON Schema検証は、ランタイムのproduction requestごとに必ず実行するものではありません。v1ではtest / CI上の契約検証として使います。productionでは観測負荷を増やさず、schemaに沿った構造をcollectorが生成します。

## 命名

想定している名前:

* Git repository: `php-unearther`
* Composer package: `coffee-r/php-unearther`
* CLI command: `unearth`
* PHP namespace: `CoffeeR\Unearther`

例:

```bash
composer require coffee-r/php-unearther
vendor/bin/unearth report logs/*.jsonl --format markdown
```

## 最初の対象範囲

最初の実装では、PHP 7.3で動くCodeIgniter3アプリケーションを優先します。アプリケーション形式はJSON APIに限定しません。HTMLを返すMVCモノリスも観測できるようにします。

Laravel 10以降は、移行先で同じschemaのログを出すための重要な対象として扱います。ただし、v1ではLaravel adapterの完全実装を成功条件にしません。まずはCodeIgniter3で実データを安全に集め、Laravel / C# などからも同じformatで出せるschemaを固めます。

初期対象:

* PHP 7.3
* CodeIgniter3 (JSON API / MVCモノリス両方)
* Laravel 10以降でも再利用できる言語非依存JSONL schema
* HTTP IN/OUT (request: JSON / form-urlencoded / multipart、response: JSON / HTML)
* OracleなどSQLベースの永続化
* Guzzle経由の外部HTTP呼び出し
* local JSONL fileへの出力
* 生成AI投入向けのredacted export

v1では対象外:

* ノンフレームワークPHP
* バッチ処理
* CSVやローカルファイルの副作用
* Laravel adapterの完全実装
* DB全体 / 行単位のbefore/after snapshot
* session差分の記録
* 完全なcall graph生成
* 完全なSQL構文解析
* endpoint別sampling
* HTTP送信sink / queue bridge
* HTML report
* Mermaid diagramの自動生成
* OpenAPIの自動更新
* テストコードの自動生成
* response time / latencyの計測 (本ツールの目的ではない)
* 既存APIと新APIの自動比較report (同じschemaのログさえ揃えば、目視 / 外部ツール / diffで比較できる)

設計は特定のアプリケーション名に閉じません。v1実装はCodeIgniter3を優先しつつ、Laravel 10以降やC#でも同じJSONL schemaを出力できるようにします。

scope削減の判断:

* Laravel adapter実装そのものはv1から削る。schemaだけ先に固める。
* HTML report、Mermaid diagram、OpenAPI補助情報、自動比較はv1から削る。
* `calls` の自動収集や完全なcall graphは削る。
* endpoint別samplingは削り、global sampling rateだけにする。
* HTTP送信sink / queue bridgeは削り、local JSONL fileを必須sinkにする。
* HTML response観測は残す。CodeIgniter3のMVCモノリス移行では画面系の調査が主戦場になり得るため。ただしHTML本文解析はせず、response metadataとview payload shapeに絞る。
* Guzzle観測は残す。決済、配送、基幹API、モール連携などの外部副作用は移行調査で重要で、Guzzle middlewareなら比較的薄く中央集約で入れられるため。

## 将来の対象

CodeIgniter3以外のレガシーフレームワーク、ノンフレームワークPHP、batch / CLIなどは積極的には追いません。必要になった場合にadapterを追加できる余地は残しますが、v1の設計判断はCodeIgniter3を優先します。

C#など他言語実装は、本packageの責務ではありません。本packageは「同じformatのログを他言語からも出しやすいよう、JSONL schemaを明示する」ことだけを担います。

## PHPバージョン

最初の実装はPHP 7.3で動作することを前提にします。

core packageでは、PHP 8以降の構文やPHP 8専用ライブラリに依存しません。

Composer constraintの例:

```json
{
  "require": {
    "php": ">=7.3"
  }
}
```

Laravel 10以降のadapterを作る場合は、新しいPHPバージョンを前提にしてよいです。PHP 7.3互換を必要とするCodeIgniter3 adapterとは、package分割またはoptional dependencyで分けます。

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

## Quick Start

CodeIgniter3では、最初にComposer autoloadを読み込む小さなbridge hookを置きます。

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

`application/config/hooks.php`に登録します。デフォルトのsampling rateは10% (`0.1`) です。

```php
$hook['pre_system'][] = array(
    'class' => 'UneartherHook',
    'function' => 'start',
    'filename' => 'UneartherHook.php',
    'filepath' => 'hooks',
    'params' => array(array(
        'service' => 'legacy-api',
        'environment' => ENVIRONMENT,
        'sample_rate' => 0.1,
        'sink' => array(
            'path' => APPPATH . 'logs/unearther-{date}.jsonl',
        ),
        'codeigniter3' => array(
            'sql_capture' => 'sampled_query_history',
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

これで、sampled requestだけDB objectの`save_queries`を一時的に有効化し、1 requestを1 JSON lineとして出力します。後からロードするDB connectionも観測したい場合は、ロード直後に登録します。

```php
use CoffeeR\Unearther\Adapter\CodeIgniter3\Hook;

$CI =& get_instance();
$CI->reporting_db = $CI->load->database('reporting', true);
Hook::observeDb($CI->reporting_db, 'reporting');
```

ログを確認したら、AI向けのredacted exportか、人間向けの簡易reportを生成します。

```bash
vendor/bin/unearth export application/logs/unearther-*.jsonl --profile ai --format jsonl
vendor/bin/unearth report application/logs/unearther-*.jsonl --format markdown --value-mode normalized
```

## 導入イメージ

Composerで配布可能なPHPライブラリとして設計しています。

```bash
composer require coffee-r/php-unearther
```

CodeIgniter3側では、できるだけ少ない差分で導入できるようにします。

想定する導入箇所:

* CodeIgniter hooksを有効化する
* `pre_controller` または `pre_system` hookでtraceを開始する
* `post_controller` または `post_system` hookで観測データをflushする
* 必要に応じて `MY_Controller` を拡張する
* view変数shapeを取りたい場合は `MY_Loader` などで `load->view()` を中央集約的にwrapする
* DB query実行箇所をwrapまたは拡張する
* Guzzle middlewareを差し込む

大きなアーキテクチャ変更を前提にしません。

Laravel 10以降では、middleware、DB listener、HTTP client middlewareなど、Laravelの標準的な拡張点で同じschemaを出せるようにします。これはv1のschema設計では意識しますが、実装優先度はCodeIgniter3より下げます。

## 本番コードへの組み込み方針

実際の振る舞いを観測するため、production applicationに何らかのコード追加は必要になります。

ただし、組み込みは薄く、中央集約的に行います。

望ましい方針:

* framework hookでtrace開始/終了を扱う
* 業務ロジックは変えない
* 各controller methodに観測用コードを散らさない
* sampling、formatting、outputはライブラリ側に閉じ込める
* 観測処理の失敗でアプリケーション本体を止めない

観測レイヤーがアプリケーションの振る舞いを変えてはいけません。

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

### HTTP

記録したいもの:

* request method
* path pattern / tokenized path / raw path (詳しくは「正規化値・token値・raw値」参照)
* route (framework上のroute識別、controller/method)
* query parameter (shape + token値 + 必要に応じてraw)
* request body (shape + token値 + 必要に応じてraw)
  * JSON、form-urlencoded、multipartを区別する
  * MVCモノリスではform POSTが主、APIではJSONが主
* response status code
* response (種別ごとに扱いを変える)
  * JSON系: shape を記録 (`capture_json_response_shape` が有効なとき)
  * HTML系: content-type、status、bodyサイズのみ。HTMLのshape化は試みない
  * view名 / template名: 安く中央集約的に取れる場合のみoptionalで記録する
  * その他バイナリ: content-type、status、サイズのみ
* client識別子は必要な場合のみ。raw IPやcookie値は保存せず、保存するならtoken化を前提にする

### HTML / View

HTML responseそのものを解析してshape化することはv1ではしません。一方で、MVCモノリスの移行では「画面に必要なデータは何か」「viewにどの変数が渡っているか」が実務上かなり重要になります。

そのため、CodeIgniter3では `load->view($view, $vars, $return)` に渡された情報を中央集約的に観測できるなら、以下をoptionalで記録します。

* view / template名
* viewに渡された変数のshape
* scalar値のtoken (`redaction.secret` がある場合のみ)
* 変数全体のサイズ、深さ、要素数の上限に達したかどうか

rawのview変数は保存しません。view変数には顧客情報、住所、メールアドレス、決済情報などが混ざる可能性が高いため、保存するのはshapeとtokenに限定します。

CodeIgniter3では、候補として `MY_Loader` で `view()` をoverrideし、`$view` と `$vars` をcollectorへ渡す方式を検討します。これは各controllerに観測コードを散らさずに済みますが、既存アプリケーションがすでに `MY_Loader` を持っている場合は統合が必要になります。v1では「取れる場合に取る」機能として扱い、HTML responseの最低限のtrace自体はview変数captureに依存させません。

例:

```json
{
  "response_kind": "html",
  "content_type": "text/html; charset=UTF-8",
  "response_bytes": 18234,
  "views": [
    {
      "seq": 1,
      "name": "orders/detail",
      "vars_shape": {
        "order": {
          "id": "string",
          "total": "number",
          "items": [
            {
              "code": "string",
              "qty": "number"
            }
          ]
        },
        "user": {
          "name": "string",
          "email": "string"
        }
      },
      "vars_tokens": {
        "order": {
          "id": "{p-b8d7210fa113}"
        }
      },
      "truncated": false
    }
  ]
}
```

### SQL

記録したいもの:

* SQL種別: `SELECT`, `INSERT`, `UPDATE`, `DELETE`, `MERGE` など
* 正規化SQL (リテラルを `{parameter}` 等に置換した形)
* raw SQL (設定で有効化されている場合のみ。後述「正規化値・token値・raw値」参照)
* statement hash (正規化SQLのhash)
* bind shape
* bind token (設定で有効化され、redaction secretがある場合のみ)
* raw bind value (明示的に有効化された場合のみ)
* 対象テーブル
* row countはv1では必須にしない。安く取れるadapterだけoptionalで記録する
* 発行元file / line / class / method

実行時間は記録しません。

### SQL解析の信頼度

v1では完全なSQL構文解析をしません。SQL analyzerは、正規化、operation判定、table抽出を軽量なbest-effort処理として行います。

ここで重要なのは、信頼度が違う情報を同じ重みで扱わないことです。

* `statement_normalized`
  * SQL pattern groupingと `statement_hash` の元になるため重要
  * literalを `{parameter}` に置換する保守的な正規化を行う
  * 正規化しきれない構文があっても、少なくともraw値を広げない方向に倒す
* `statement_hash`
  * `statement_normalized` から計算する
  * table抽出に失敗しても、同じSQL patternを束ねる用途では使える
* `operation`
  * `SELECT`、`INSERT`、`UPDATE`、`DELETE`、`MERGE` など先頭付近のDMLをbest-effortで判定する
  * CTE (`WITH ... SELECT`) などでは主操作を誤る可能性があるため、判定不能なら `UNKNOWN` にする
* `tables`
  * `FROM`、`JOIN`、`UPDATE`、`INSERT INTO`、`MERGE INTO` などからbest-effortで抽出する
  * OracleのMERGE、CTE、サブクエリ、quoted identifier、schema名、動的SQLでは漏れや誤検出があり得る
  * v1では完全性を保証しない

このため、SQL eventには解析メタデータを持たせます。

```json
{
  "operation": "SELECT",
  "tables": ["M_SHOHIN"],
  "analysis": {
    "analyzer": "regex",
    "operation_confidence": "high",
    "tables_confidence": "best_effort",
    "warnings": []
  }
}
```

reportでは、table抽出を「観測された確定事実」として強く言い切りません。特に `tables_confidence` が `best_effort` / `unknown` の場合は、SQL flowや参照/更新テーブルの表示に注記を付けます。移行調査で最終確認が必要な場合は、`statement_normalized`、`statement_hash`、caller、必要に応じてraw SQLを見て人間が確認します。

v1で保証するのは「SQLがこの順番で実行された」「同じnormalized SQL patternが何回出た」ことを中心にします。table一覧は便利な補助情報ですが、完全な依存関係解析ではありません。

### DB before/after snapshot

v1では取りません。

「影響行のみを絞り込んでスナップショットを撮る」という方向は技術的には不可能ではない (UPDATE/DELETE前にWHERE条件で`SELECT *`を流す、INSERT後にPKで読む、など) ですが、現スコープと相性が悪いです。

* 影響行を特定するため、SQL本文 / Query Builder状態からWHEREを取り出して再構成する必要がある。これはv1対象外の「完全なSQL構文解析」に踏み込む。
* 書き込み1回ごとにSELECTが追加で走る。bulk UPDATEや大量行操作で本番負荷が無視できないレベルに跳ねる。
* triggerやFK cascadeなど、対象テーブルの外で走る変更や、SELECTを通さない変更は拾えない。
* transaction isolation、ロックの取り方、巨大行のserialize方針なども別途設計が必要。

観測レイヤーは「薄く、中央集約で」という方針なので、v1からは外します。再検討するなら optional pluginとして、以下のように責務を絞った前提で設計します。

* 単一PK更新のみ (`WHERE pk = ?` パターン)
* 明示的に有効化されたendpoint / tableに限定
* 1 requestあたりの上限件数あり
* 失敗時は黙ってskip

### 正規化値・token値・raw値

SQL、HTTPパス、query、request bodyなどは、見たい場面によって適した表現が違います。

v1では以下の3段階で扱います。

* normalized: 具体値を完全に潰した形。集計とpattern分類の基本にする
* tokenized: 具体値は戻せないが、同じ値かどうかは分かる形。生成AIに渡すredacted exportで使える
* raw: 実値そのもの。人間がローカルで調査したい場合だけ明示的に有効化する

| 種別 | normalized | tokenized | raw |
|---|---|---|---|
| SQL本文 | `SELECT * FROM users WHERE id = {parameter} AND status = {parameter}` | `SELECT * FROM users WHERE id = {p-a1b2c3d4} AND status = {p-e5f6a7b8}` | `SELECT * FROM users WHERE id = 123 AND status = 'active'` |
| bind value | `number` / `string` | `{p-a1b2c3d4}` | `123` |
| HTTP path | `/api/users/{id}/orders/{order_id}` | `/api/users/{p-a1b2c3d4}/orders/{p-b2c3d4e5}` | `/api/users/123/orders/456` |
| query parameter | shape `{page: number, sort: string}` | `{page: "{p-a1b2c3d4}", sort: "{p-e5f6a7b8}"}` | `?page=2&sort=date` |
| request body | shape `{customer_id: string, items: [{code: string, qty: number}]}` | `{customer_id: "{p-a1b2c3d4}", items: [{code: "{p-e5f6a7b8}", qty: "{p-b2c3d4e5}"}]}` | raw value |

用途:

* 正規化版 → endpoint / SQL pattern grouping、statement_hash、execution pattern分類、件数集計
* tokenized版 → 具体値を出さずに「同じ値が複数箇所に出たか」「同じ顧客/商品/注文らしき値で分岐したか」を見たい時
* raw版 → 「具体的にどんなSQLが来たか」「どんなidで呼ばれたか」をローカルで人間が確認したい時

方針:

* 正規化版は常に保存する (これがないと集計できない)
* tokenized版は、`redaction.secret` が設定されている場合に保存できる
  * tokenは `HMAC-SHA256(canonical_value, redaction.secret)` の先頭8〜12文字などから作る
  * 例: `{p-a1b2c3d4}`
  * saltなしの単純hashは使わない。候補が少ない値を辞書攻撃で戻せるため
  * `redaction.secret` がない場合、AI向けexportではtokenを出さず `{parameter}` に落とす
* raw版は設定でon/offできる。デフォルトはoff (個人情報、認証情報、決済情報の混入リスクがあるため)
  * `sql.capture_text` でraw SQL on/off
  * `sql.capture_bind_raw` でraw bind value on/off
  * `http.capture_raw_path`、`http.capture_raw_query` でHTTPのraw値 on/off
  * `http.capture_raw_body` はさらに慎重に扱い、原則off
* endpointごとの細かいcapture制御はv1では必須にしない
* HTTP pathのcanonical化 (`{id}`化) は `http.endpoint_patterns` で設定したパターンを使う
* マッチしないpathは、数字やUUIDらしきsegmentを保守的に `{parameter}` または `{p-...}` に潰す。raw pathをそのままcanonicalとして扱わない
* statement_hashは正規化SQLから計算する。raw SQLの保存有無に関係なく一貫させる
* 生成AIにreportやlogを入力する場合は、redacted exportを使うように案内する

report / export側は、デフォルトでnormalizedまたはtokenizedを使います。raw値の表示は、観測時にraw captureが有効で、かつ明示的にraw modeを指定した場合だけにします。

### Session

v1ではsession差分の記録を対象外にします。

### 外部HTTP呼び出し

v1ではGuzzleによる外部呼び出しを記録します。

curl直接呼び出しや独自HTTP helperのhookは初期対象外にします。

記録したいもの:

* 呼び出し先host
* method
* path
* request shape
* response status code
* response shape
* timeout / retry / error
* 呼び出し元file / line / class / method

決済、配送、基幹API、モール連携などは重要な副作用になり得るため、同じ`trace_id`で束ねます。

## サンプリングと本番負荷

本番環境で低負荷に動かせることを前提にします。

方針:

* sampling rateを設定可能にする
* 最初は1%程度など低いrateから始める
* 必要に応じて10%や20%に上げられるようにする
* v1ではglobal sampling rateのみを必須にする
* endpointごとのsampling rateは将来拡張に回す
* 引数captureはデフォルトでは行わない
* DB snapshotは取らない
* `debug_backtrace`は `DEBUG_BACKTRACE_IGNORE_ARGS` のみ使う (引数付きbacktraceは取らない)
* 1 requestにつき1 JSON lineを出力する
* ログ書き込みに失敗してもアプリケーション本体を止めない

観測レイヤーは補助機能です。アプリケーション本体の正常動作を優先します。

## 観測範囲と限界

php-uneartherが出すのは、仕様そのものではなく「観測された事実」です。

そのため、reportやAI向けexportでは次を明確に扱います。

* 観測されたものは、実際にそのrequest期間・sampling条件で発生した事実
* 観測されなかったものは、存在しないことの証明ではない
* サンプリング率、観測期間、流したシナリオ、本番/検証環境の違いによってcoverageは変わる
* 低頻度の分岐、月次処理、特定ユーザーだけの分岐、エラー時だけのSQLは漏れる可能性がある

v1では、coverageを良くするための魔法は入れません。できることは以下に絞ります。

* sampling rateを上げる
* 観測期間を伸ばす
* 重要画面・重要endpointを手動で操作して観測する
* QA / migration rehearsalで代表シナリオを流す
* report / exportに `observed_count`、`first_seen_at`、`last_seen_at`、`sample_rate`、`environment` などcoverage判断に必要なメタデータを含める

この前提を明示すれば、生成AIに渡す場合も「未観測の分岐を勝手に仕様として補完しない」ように指示しやすくなります。

## Masking と保存先

v1では高度なmasking policy engineを中核機能にしません。

ただし、生成AIや外部ツールに渡す前の安全化は中核機能にします。

観測データには個人情報、認証情報、決済関連情報が混ざる可能性があるため、デフォルトではraw valueではなくshape、normalized値、token、分類、件数を優先して保存します。個別keyのmasking ruleやwhitelist / blacklist方式は、必要になった場合の拡張候補とします。

v1のmaskingは以下に絞ります。

* raw系フィールドを落とす
* SQL literal / bind value / path segment / query value / request body valueをnormalized化する
* `redaction.secret` がある場合は、値の同一性を追えるHMAC token (`{p-...}`) を出す
* secret、token、password、authorization、cookieなど明らかに危険なkeyはtoken化もせず `{redacted}` に落とす簡易denylistを持つ
* 複雑な業務別masking ruleはv1では持たない

### 生成AI / 外部ツールへログを渡す場合

観測ログを生成AIに要約させたい、pattern命名させたい、別組織の人にreportを見せたいといった用途では、raw値が含まれていると個人情報・認証情報・決済情報の流出経路になります。

このため:

* AI / 外部ツール送付向けには、 **redacted subset** を取り出せるようにします
  * `statement_normalized`、`statement_hash`、`path_pattern`、`query_shape`、`request_shape`、`response_shape`、`bind_shape` などshape / 正規化系を含める
  * `*_tokens` や `statement_tokenized` は、`redaction.secret` が設定されている場合のみ含める
  * `statement_text`、`path_raw`、`query_raw`、`request_raw`、`bind_raw` などraw系は落とす
* このsubset生成はpackage側にCLIまたはreport生成オプションとして用意します (例: `unearth export --profile ai`、`unearth report --redacted` など)
* normalized版に何も具体値が残らないことを保証するため、SQL正規化と path canonicalizationは「保守的に強め」 (リテラルは積極的に `{parameter}` 化、未マッチpathは数字 / UUID / 長いtoken風segmentを潰す) にします
* それでもtable名、column名、endpoint pathの構造自体が機密扱いの場合はあるので、最終判断は導入側に委ねます

ログの保存先は差し替え可能にします。

将来的に想定する出力先:

* local JSONL file
* application server上の任意path
* 別の集約サーバーへのHTTP送信
* queueやlogging基盤へのbridge

ただし、v1の必須sinkはlocal JSONL fileに絞ります。集約サーバー自体やログ基盤の運用機能は、このpackageの必須責務にしません。packageの責務は、観測イベントを同じschemaで生成し、必要に応じて安全なsubsetへ変換することです。

## 観測データ例

```json
{
  "trace_id": "20260601T101122-3f4e3f8b9c0d4d67",
  "service": "legacy-api",
  "framework": "codeigniter3",
  "environment": "production",
  "schema_version": 1,
  "sampled": true,
  "sample_rate": 0.1,
  "started_at": "2026-06-01T10:11:22+09:00",
  "redaction": {
    "tokenized": true,
    "token_format": "hmac-sha256:12"
  },
  "http": {
    "method": "POST",
    "path_pattern": "/api/users/{id}/cart",
    "path_tokenized": "/api/users/{p-a1b2c3d4e5f6}/cart",
    "path_raw": null,
    "route": "Cart/add",
    "status": 200,
    "response_kind": "json",
    "content_type": "application/json",
    "response_bytes": 48,
    "query_shape": {},
    "query_tokens": {},
    "query_raw": null,
    "request_shape": {
      "item_code": "string",
      "quantity": "number"
    },
    "request_tokens": {
      "item_code": "{p-c9418d2a77a1}",
      "quantity": "{p-47fdc8f45120}"
    },
    "request_raw": null,
    "response_shape": {
      "result": "string",
      "cart_count": "number"
    },
    "views": []
  },
  "sql": [
    {
      "seq": 1,
      "operation": "SELECT",
      "tables": ["M_SHOHIN"],
      "statement_normalized": "SELECT * FROM M_SHOHIN WHERE item_code = {parameter}",
      "statement_tokenized": "SELECT * FROM M_SHOHIN WHERE item_code = {p-c9418d2a77a1}",
      "statement_text": null,
      "statement_hash": "sha256:...",
      "bind_shape": ["string"],
      "bind_tokens": ["{p-c9418d2a77a1}"],
      "bind_raw": null,
      "analysis": {
        "analyzer": "regex",
        "operation_confidence": "high",
        "tables_confidence": "best_effort",
        "warnings": []
      },
      "caller": {
        "file": "application/models/Cart_model.php",
        "line": 123,
        "class": "Cart_model",
        "function": "find_item"
      }
    },
    {
      "seq": 2,
      "operation": "INSERT",
      "tables": ["T_CART"],
      "statement_normalized": "INSERT INTO T_CART (customer_id, item_code, quantity) VALUES ({parameter}, {parameter}, {parameter})",
      "statement_tokenized": "INSERT INTO T_CART (customer_id, item_code, quantity) VALUES ({p-a1b2c3d4e5f6}, {p-c9418d2a77a1}, {p-47fdc8f45120})",
      "statement_text": null,
      "statement_hash": "sha256:...",
      "bind_shape": ["string", "string", "number"],
      "bind_tokens": ["{p-a1b2c3d4e5f6}", "{p-c9418d2a77a1}", "{p-47fdc8f45120}"],
      "bind_raw": null,
      "analysis": {
        "analyzer": "regex",
        "operation_confidence": "high",
        "tables_confidence": "best_effort",
        "warnings": []
      },
      "caller": {
        "file": "application/models/Cart_model.php",
        "line": 188,
        "class": "Cart_model",
        "function": "insert_cart"
      }
    }
  ],
  "external_http": [
    {
      "kind": "external_http",
      "host": "example-payment.local",
      "method": "POST",
      "path": "/payments/authorize",
      "status": 200,
      "request_shape": {
        "order_id": "string",
        "amount": "number"
      },
      "request_tokens": {
        "order_id": "{p-b8d7210fa113}",
        "amount": "{p-c9f5a92b4110}"
      }
    }
  ]
}
```

`schema_version`はlog formatのbreaking changeを示すための整数。version 1を起点にします。

raw値 (`request_raw` / `query_raw` / `path_raw` / `statement_text` / `bind_raw` 等) はデフォルトで `null`。設定で有効化された場合のみ実値が入ります。

token値 (`request_tokens` / `query_tokens` / `path_tokenized` / `bind_tokens` 等) は、`redaction.secret` が設定されている場合のみ安定して出せます。生成AIに渡す場合は、raw値を含まないredacted exportを使います。

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
    'environment' => 'production',
    'failure_mode' => 'throw',
    'sample_rate' => 0.1,
    'sink' => array(
        'type' => 'jsonl',
        'path' => APPPATH . 'logs/unearther-{date}.jsonl',
        'date_format' => 'Y-m-d',
    ),
    'codeigniter3' => array(
        'sql_capture' => 'sampled_query_history',
    ),
    'sql' => array(
        'capture_text' => false,
        'capture_bind_raw' => false,
    ),
    'redaction' => array(
        'secret' => null,
        'token_length' => 12,
        'deny_keys' => array('secret', 'token', 'password', 'authorization', 'cookie'),
    ),
    'shape' => array(
        'max_depth' => 6,
        'max_items' => 100,
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

`codeigniter3.sql_capture`には`sampled_query_history`、`wrapped_db`、`none`を指定できます。古い`query_history`は`sampled_query_history`、古い`observed_db`は`wrapped_db`への互換aliasとして受け付けます。古い`codeigniter3.capture_query_history`キーも互換性のエイリアスとして引き続き受け付けます。

`sql.capture_text`はデフォルトでオフです。SQLイベントは常に`statement_normalized`（リテラルを`{parameter}`に置換）と`statement_hash`を出力します。`capture_text`を`true`にすると生SQLが`statement_text`にも書き込まれます；それ以外は`null`です。テキストキャプチャの有効化は移行分析には有用ですが、アプリケーションがリテラルでSQLを構築している場合に機密値を露出する可能性があります。

`sql.capture_bind_raw`もデフォルトでオフです。`wrapped_db`などbind値を取れる経路でのみ意味があります。AI向けexportでは`bind_raw`は常に除去されます。

`shape.max_depth`と`shape.max_items`は、巨大な配列・object・循環参照をview変数やrequest bodyから辿り続けないための上限です。上限に達した場合、shapeにはtruncated markerが残ります。

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
        'sample_rate' => 0.1,
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

デフォルトでは、CodeIgniter3フックは`sql_capture => sampled_query_history`を使用します。これは、サンプリング対象になったリクエストだけDB objectの`save_queries`を一時的に`true`へ切り替え、リクエスト終了時に`$db->queries`を読み取る方式です。常時`save_queries`を有効化しないため、未サンプリングのリクエストではCodeIgniterのクエリ履歴保持コストを避けられます。

hook開始時点で`$CI->db`が存在する場合は自動登録します。後からロードされるDB connectionや複数DB connectionを観測したい場合は、ロード直後に`Hook::observeDb($db, $name)`を呼んでください。

```php
use CoffeeR\Unearther\Adapter\CodeIgniter3\Hook;

$CI =& get_instance();
$CI->reporting_db = $CI->load->database('reporting', true);
Hook::observeDb($CI->reporting_db, 'reporting');
```

この戦略にはトレードオフがあります：

- サンプリングされたリクエスト内の共通処理、認証、session DBなどのqueryも同じtraceに入る
- 正確なcallerのファイル/行情報が得られない
- bind値そのものではなく、CodeIgniterが保持する最終SQL文字列が中心になる
- サンプリングされた1リクエストで多数のクエリが実行される場合、メモリ使用量が増加する可能性がある

このため、query history由来のSQL eventには`analysis.warnings`として`query_history_capture_has_no_precise_caller_or_bind_values`が入ります。

SQLキャプチャを無効化することもできます：

```php
'codeigniter3' => array(
    'sql_capture' => 'none',
)
```

callerやbind情報の精度を優先したい場合は、advanced optionとしてDBラッパーも利用可能です：

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

`ObservedDb`を使用する場合は、フックがCodeIgniterのクエリ履歴を二重に記録しないよう`sql_capture => wrapped_db`を設定してください：

```php
'codeigniter3' => array(
    'sql_capture' => 'wrapped_db',
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

### View / Templateキャプチャ (v1ではoptional)

`load->view($view, $vars, $return)` 経由でレンダリングされるviewについて、view名 + 変数shape (+ secret設定時にtoken) を観測したい場合は、`MY_Loader` を用意して `view()` を中央集約的にwrapする方式を検討します。詳細は「観測モデル / HTML / View」を参照してください。

v1ではこの機能は opt-in 扱いで、CodeIgniter3 adapter本体は最低限のHTML traceがview captureに依存しないようにします。

## トレースID

各サンプリングされたリクエストは、観測開始時に生成されたトレースIDを受け取ります。IDにはUTCタイムスタンプのプレフィックスと128ビットのランダムなサフィックスが含まれます：

```text
20260601T101122-3f4e3f8b9c0d4d67a1b2c3d4e5f60718
```

これにより、開始時刻でのソートが可能になりながら、通常のサンプリング量では衝突が実質的に発生しません。

## ログローテーション

JSONLシンクはパスの`{date}`プレースホルダーによる日次ファイルローテーションをサポートしています：

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

## Export / レポート生成

観測ログから、移行調査に使えるexportや簡易レポートを生成します。

v1で最も重要なのは、生成AIや外部ツールに投入しやすい **redacted export** を出すことです。高度な要約、pattern名付け、HTML表示、移行差分の解釈は生成AIや別ツールに委ねます。

簡易レポートは、一般的なAPI仕様書というより、実際に観測されたendpointの挙動を機械的に集計したものに留めます。特に、同じendpoint内にあるSQL実行順や外部HTTP呼び出しの分岐パターンを見えるようにします。

例:

```bash
vendor/bin/unearth export logs/*.jsonl --profile ai --format jsonl
vendor/bin/unearth export logs/*.jsonl --profile ai --format json
vendor/bin/unearth report logs/*.jsonl --format markdown --value-mode normalized
vendor/bin/unearth report logs/*.jsonl --format json --value-mode tokenized
```

初期出力形式:

* redacted JSONL
* aggregate JSON
* 簡易Markdown

将来的な出力候補:

* HTML
* Mermaid diagram
* OpenAPI補助情報

最初はredacted JSONLとaggregate JSONを優先します。Markdownは人間がざっと読むための補助として扱いますが、表現力を作り込みすぎません。

JSONは生成AI、機械処理、独自UIへの入力に使います。Markdownはレビューしやすく、Gitで差分管理しやすく、共有しやすいので残します。

AIを通さず、観測ログから決定論的に生成できるexport / reportを基本にします。AIによる要約やpattern名の意味づけは、このpackage本体の責務にしません。

既存/新APIの比較は本packageでは扱いません。同じschemaのログが両側にあれば、人間 / 別ツール / 単純なdiffで比較できます。

report / exportには10MBなどの固定サイズ上限は設けません。大きなログを生成AIや外部ツールへ渡す場合は、利用者側で期間、endpoint、環境、sample file単位に入力JSONLを分割してください。php-uneartherは、渡された入力に対して決定論的にredacted export / aggregate reportを生成します。

レポートはエンドポイントと観測された実行パターンでトレースをグループ化します。パターンは現在、SQLの操作/テーブル/ハッシュのフローとGuzzle外部HTTP呼び出しに基づいています。パターンの粒度はこのプロトタイプでは固定されており、設定できません。

キャプチャ時に`http.endpoint_patterns`が設定されている場合、レポートは記録された`path_pattern`でグループ化されます。レポートにはエンドポイントレベルのエラー数と、トレースの`errors`からグループ化されたエラーサマリーも含まれます。

Markdownレポートの各パターンは`#### Representative Case`ブロックで終わり、そのパターンで最初に観測されたトレースの名前、`path (canonical)`と`path (concrete)`、`statement_normalized`形式のSQLリスト、および外部API呼び出しが示されます。`--value-mode raw`を明示し、かつキャプチャ時に`sql.capture_text`が有効だった場合だけ、正規化された形式の横に具体的なSQLも表示されます。

集計レポートとMarkdownレポートは、デフォルトでは生SQLテキストをレンダリングしません。SQLフローテーブルには常に`statement_hash`と`statement_normalized`が含まれます。`--value-mode tokenized`ではtokenが存在する場合だけtokenized値も併記されます。

CLIは`report`と`export`をサポートしています。`compare`コマンドや`--from` / `--to`による日付フィルタリングは含まれていません。

パターンの例：

```text
SELECT M_SHOHIN -> INSERT T_CART
```

## Export / レポートに含めたいもの

endpointごとに以下を出します。

* method / path
* route / controller / method
* 観測件数
* request shape
* request tokens (redaction secretがある場合)
* response shape
* status code分布
* 観測されたexecution pattern
* patternごとのSQL flow
* 参照テーブル
* 更新テーブル
* 外部HTTP呼び出し
* error
* 代表的なcase

代表的なcaseでは以下を見られるようにします。

* input shape
* input tokens (redaction secretがある場合)
* output shape
* SQL (正規化版が基本。tokenがあれば併記。raw SQLは明示的なraw modeの場合のみ表示)
* SQL発行元
* 外部HTTP呼び出し
* HTTP path pattern
* HTTP path tokenized
* query shape / tokens

execution patternは、各requestのSQL実行順、SQL種別、対象テーブル、statement hash、外部HTTP呼び出し、status codeなどから機械的に分類します。分類粒度は固定で、設定可能にしません。

固定方針:

* SQL列 → `operation + tables + statement_hash` のシーケンスでpattern keyを作る
* 外部HTTP呼び出しは host / method / pathで束ねた要素として加える
* status codeも含める

SQL本文の完全一致、bind token、bind shapeの差異は同じpattern内のバリエーションとして扱います。

report出力時、SQL / HTTP path / query は次の3モードで切り替えられるようにします。

* normalized (default): `WHERE id = {parameter}`、`/users/{id}` のように具体値を伏せた形。pattern集計、AI / 外部ツールへの受け渡しに向く
* tokenized: `WHERE id = {p-a1b2c3d4}`、`/users/{p-a1b2c3d4}` のように具体値を戻せないが同一性は追える形。AI / 外部ツールへの受け渡しに向く
* raw / concrete: `WHERE id = 123`、`/users/123` のように観測された具体値そのもの。ローカルで人間が「実際にどんな値が来てるか」を見たい時だけ使う

raw値の表示は、観測時に有効化されていた場合のみ可能です。observation logにraw値が無い場合は normalized fallbackになります。

## 簡易Markdown例

````md
# Observed API Behavior Report

## POST /api/cart/add

- Controller: Cart::add
- Observed requests: 1,284
- Error rate: 0.3%

### Request Shape

| Field | Type | Notes |
|---|---|---|
| item_code | string | observed |
| quantity | number | observed |
| customer_id | string | observed |

### Response Shape

| Field | Type | Notes |
|---|---|---|
| result | string | observed |
| cart_count | number | observed |

### Observed Execution Patterns

| Pattern | Count | Status | SQL Flow | Tables | External Calls |
|---|---:|---|---|---|---|
| pattern-1 | 1,120 | 200 | SELECT M_SHOHIN -> INSERT T_CART | M_SHOHIN, T_CART | none |
| pattern-2 | 164 | 400 | SELECT M_SHOHIN | M_SHOHIN | none |

### Pattern: pattern-1

Observed: 1,120 requests

#### SQL Flow

| Step | Operation | Tables | Count | Example Source |
|---:|---|---|---:|---|
| 1 | SELECT | M_SHOHIN | 1,120 | Cart_model.php:123 |
| 2 | INSERT | T_CART | 1,120 | Cart_model.php:188 |

### Representative Case

- trace_id: `20260601T101122-a8f3`
- status: `200`
- path pattern: `/api/users/{id}/cart`
- path tokenized: `/api/users/{p-a1b2c3d4}/cart`
- query: `{}` (none observed)
- SQL count: `2`
  - `SELECT * FROM M_SHOHIN WHERE item_code = {parameter}` (tokenized: `... = {p-c9418d2a}`)
  - `INSERT INTO T_CART (...) VALUES ({parameter}, {parameter}, {parameter})`
- external API calls: none

raw modeを明示した場合だけ concrete value を表示します。AI向けexportや通常reportではrawを出しません。
````

## Adapter設計

core packageが持つもの:

* observation schema
* JSON Schema specification
* sampling policy
* event collector
* normalizer
* redactor / tokenizer
* sink interface
* JSONL writer
* redacted exporter
* simple aggregate reporter

framework固有の処理はadapterに分けます。

対象adapter:

* CodeIgniter3 adapter (v1優先)
* Laravel 10以降 adapter (後続。schema設計上は常に意識する)

CodeIgniter3 adapterの責務:

* hookによるrequest lifecycle tracking
* controller / method検出
* DB query historyまたはDB wrapperによるSQL観測
* Guzzle middleware連携
* HTML responseのcontent-type / status / body size記録
* `MY_Loader` などによるview名 / template名 / view変数shapeのoptional記録

Laravel 10以降 adapterの責務:

* middlewareによるrequest lifecycle tracking
* DB listenerによるSQL tracking
* HTTP client / Guzzle middleware tracking
* CodeIgniter3 adapterと同じJSONL schemaを出力する

## 引数captureについて

PHPでは一部のケースで関数引数をcaptureできますが、php-uneartherでは広範な自動引数captureには依存しません。

優先する方法:

* wrapper function / class
* framework listener
* middleware
* 明示的なinstrumentation point

`debug_backtrace`の扱い:

* caller file / lineの取得には `debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)` のみ使う
* 引数付きの `debug_backtrace()` は使わない (重い + 個人情報・認証情報・巨大objectが意図せず混入するリスク)
* raw request bodyを無条件に保存しない

## APM / OpenTelemetryとの関係

php-uneartherはAPMやOpenTelemetryの概念と重なる部分がありますが、目的が違います。

APMの主目的:

* latency
* error
* trace
* service map
* infrastructure visibility

php-uneartherの主目的:

* 観測されたendpoint挙動
* SQL pattern発掘
* 外部API呼び出し発掘
* 生成AIや人間が分析できるredacted observation data生成
* 移行先実装でも同じformatのlogを出せるよう、log schemaを言語非依存に固める

response timeやlatencyは取りません。

既存APMやOpenTelemetry packageの考え方は参考にします。ただし、PHP 7.3とCodeIgniter3対応のため、軽量な独自実装が必要になる可能性が高いです。

## v1成功条件

v1は以下を満たせば成功とします。

* PHP 7.3のCodeIgniter3 APIでsampled observation logを出力できる
* Laravel 10以降やC#などでも同じschemaのlogを出せるよう、JSONL schemaが文書化されている
* `docs/schema/observation-v1.schema.json` があり、fixtureや生成sampleをPHPUnitでschema検証できる
* sampling rateを設定できる
* 1 requestが1 JSON lineになる
* HTTP input/output、controller、SQL、tableが`trace_id`でつながる
* Guzzle external HTTP callが`trace_id`でつながる
* SQL / HTTP path / query / request bodyをnormalized版で扱える
* SQL analyzerの信頼度 (`operation_confidence` / `tables_confidence`) とwarningをlog / reportに出せる
* `redaction.secret` がある場合、値の同一性を追える `{p-...}` tokenを出せる
* raw値は明示的に有効化された場合のみ保存される
* 生成AIや外部ツールに渡す用途のためのredacted exportを生成できる
* endpoint単位のJSON aggregate reportを生成できる
* endpoint単位の簡易Markdown reportを生成できる
* SQL pattern、execution pattern、代表caseを確認できる
* report / exportに観測件数、観測期間、sample rateなどcoverage判断に必要な情報を含められる
* CodeIgniter3のMVCモノリス (HTML responseを返す形式) でも最低限のtraceを記録できる
* CodeIgniter3で中央集約的に取れる場合、view名とview変数shapeを記録できる

## 開発

```bash
composer install
vendor/bin/phpunit
php bin/unearth report tests/Fixtures/jsonl/cart_add.jsonl --format md
```

CodeIgniter3で実際に組み込めるかを確認するためのDocker e2eもあります。PHP 7.3 + CodeIgniter3 + SQLiteの小さなEC fixtureを起動し、実HTTP request、SQL query history、`MY_Loader`経由のview capture、Guzzle external HTTP、CLI export / reportまでを通します。

```bash
composer e2e:ci3-sqlite
```

このe2eはDocker imageのpull/buildとComposer installを含むため、通常のunit testより重いです。実行時はfixtureのCompose stackを作り直し、SQLite schema / seed dataを初期化してから検証します。手動でfixtureを起動してcurlする場合も、先にコンテナ内で以下を実行してください：

```bash
docker compose -f tests/e2e/codeigniter3-sqlite/docker-compose.yml -p php-unearther-ci3-sqlite exec -T --user www-data app php scripts/init-db.php
curl -i 'http://localhost:18080/api/products?category_id=1'
```

CodeIgniter3 hookの`params`は可変長引数として展開されず、hook methodへ1引数として渡されます。そのためfixtureでは`'params' => $uneartherConfig`の形で設定配列を直接渡します。

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
- JSON schema contract
- redacted export
- sampled query historyの`save_queries`切替
- redaction token
- view変数shapeと循環参照対策
- CodeIgniter3 SQLite e2e fixtureによる実HTTP / SQL / view / Guzzle / CLI検証

## 未決事項

* CodeIgniter3で `MY_Loader` が既にある場合の統合方法
* Laravel 10以降 adapterをいつ実装するか
* `redaction.secret` のrotation運用方針
* business-specific masking ruleをどの粒度で追加するか
* CodeIgniter3の複数DB connection登録を既存アプリ側のどこに置くか

## OpenAI Codexで構築

このプロトタイプはOpenAI Codexの助けを借りて設計・実装されました。

## ライセンス

MIT
