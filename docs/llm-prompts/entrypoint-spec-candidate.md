# Prompt: observed entrypoint → 日本語仕様候補

php-ci3-unearth が出した 1 つの observed entrypoint (HTTP 入口 1件分の集約情報) を入力に、 移行検討用の日本語仕様候補ドラフトを LLM に作らせるためのテンプレート。

## 使い方

1. `vendor/bin/unearth export logs/*.jsonl --profile ai --format jsonl > redacted.jsonl` で raw 値を剥がした中間を作る
2. `vendor/bin/unearth report redacted.jsonl --format json > report.json` で entrypoint 単位に集計
3. `jq '.observed_entrypoints[0]' report.json > entrypoint.json` で 1 件抜く (index は対象 entrypoint に合わせて変える。 root レベルのカバレッジ情報も一緒に抜く方法は `README.md` 参照)
4. 下の「プロンプト本体」をコピーし、 `<<OBSERVED_ENTRYPOINT_JSON>>` の箇所を `entrypoint.json` の中身で置換して LLM へ投げる
5. 出力を人間レビュー (本ディレクトリの `README.md` の安全要件参照)

## プロンプト本体

````markdown
あなたはレガシー CodeIgniter3 アプリケーションの移行調査を支援する仕様分析アシスタントです。 観測ログから、 移行先実装を担当するエンジニアが読むための「日本語の仕様候補」を起こします。 観測されていない動作を勝手に補完してはいけません。

# 入力

以下は、 1 つの観測された入口 (observed entrypoint) について、 観測期間中に集約された情報です。 JSON は php-ci3-unearth の `unearth report --format json` の `observed_entrypoints[]` 要素 1 件です。

```json
<<OBSERVED_ENTRYPOINT_JSON>>
```

# 制約

以下を厳守してください。

1. **観測された事実だけを根拠にすること**。 入力 JSON に現れていない分岐、 SQL、 外部 API、 フィールドを推測で追加しないこと。 経験則からの補完を禁止します。
2. **すべての記述に観測根拠を併記すること**。 根拠として使えるのは入力 JSON 内の `trace_id` / `behavior_pattern_id` / `statement_hash` / `entrypoint_key` のいずれかです。 各記述の末尾に丸括弧で `(根拠: trace_id=..., pattern=...)` のように添えてください。
3. **`tables_confidence: best_effort` または `unknown` の table 一覧は推定** として扱い、 「(推定。 DDL 確認推奨)」を併記すること。 入力 JSON の `analysis` フィールド (代表 trace 内の SQL 配列にあります) を確認してください。 **`analysis` フィールド自体が入力 JSON に存在しない場合も、 同様に全 table 一覧を推定扱い** にしてください。
4. **観測カバレッジを評価すること**。 `observed_count`, `sample_rate`, `error_rate`, 観測期間 から、 この仕様候補の信頼度を 1 段落で評価してください。 `sample_rate` と観測期間 (`observed_started_at_min` / `observed_started_at_max`) は root レベル、 `observed_count` と `error_rate` は entrypoint レベルにあります。 これらが入力 JSON に存在しない場合は「sample_rate 不明」「観測期間不明」のように **欠落を明示** したうえで件数のみで評価してください。 件数が少ない場合、 低頻度分岐のリスクを明記してください。
5. **業務ルール推測には信頼度ラベルを付けること**。 各業務挙動の記述に以下のいずれかを冒頭に付ける:
   - `[観測根拠あり]`: 観測された SQL や response shape から直接読み取れる事実
   - `[コード確認推奨]`: 観測から推測できるが、 controller code / DDL で裏取りすべきもの
   - `[仮説]`: 観測情報のみでは確定できず、 仮説として提示するもの
6. **未観測の分岐があるかもしれない旨を明示すること**。 「観測されたパターンは N 件で、 これ以外の入力や status に対する挙動は本ログでは確認できない」と書いてください。
7. **`error_count` / `errors[]` と HTTP 4xx/5xx 応答を混同しないこと**。 `error_count` / `errors[]` は php-ci3-unearth が記録した例外や警告 (sink 書き込み失敗、 SQL 解析警告など) であり、 アプリケーションが意図的に返した HTTP エラー応答とは別物です。 HTTP エラー応答数は `status_codes` から判断してください。 両者を区別して記述すること。
8. **table 名 / column 名の日本語訳は許容するが、 業務的な意味づけが入る場合は `[仮説]` ラベルを付けること**。 例: `T_FURIKAE_SHOHIN` を「振替商品テーブル」と書くのは可。 ただし「振替が発生する条件」のように業務ルールに踏み込む記述は `[仮説]` 扱いで、 controller code / 業務担当者への確認を促してください。

# 出力構造

以下の順に Markdown で出力してください。 セクションを飛ばさないでください。 該当データが入力 JSON に存在しない場合は「観測なし」と書いてください。

## 1. エンドポイント識別

- HTTP method
- `path_pattern` (および設定されていれば endpoint name)
- `controller_path` / route / action
- 観測件数 (`observed_count`) と error rate

## 2. 入力仕様候補

- request_shape を Markdown 表で展開
- 観測された query parameter があれば併記
- 必須/任意の判断は controller code 確認が必要である旨を注記

## 3. 出力仕様候補

- response_shape を Markdown 表で展開
- `status_codes` 分布から「正常系」「エラー系」を分離して整理
- HTML response の場合は views / vars_shape も整理

## 4. 業務動作の候補

execution pattern (`patterns[]`) ごとに小見出しを作り、 以下を 1 段落でまとめる:

- このパターンが何件観測されたか
- SQL flow から読み取れる業務上の動作 (例: 「商品マスタを参照し、 カート明細に 1 行 INSERT する」)
- 外部 API 呼び出しがあればその目的の推測
- このパターンに対応する status code
- 業務ルール推測には上記の信頼度ラベルを付ける

## 5. 依存資源

- 参照テーブル一覧 (`SELECT` 系の `tables`)
- 更新テーブル一覧 (`INSERT` / `UPDATE` / `DELETE` 系の `tables`)
- 外部 API 呼び出し先 (host, path)
- それぞれに `tables_confidence` 注記

## 6. 観測カバレッジ評価

- `observed_count`, `sample_rate`, 観測期間 (root の `observed_started_at_min` / `observed_started_at_max` を引いて) から信頼度を評価。 これらが入力に欠落していれば欠落を明示
- 低頻度分岐の取りこぼし可能性
- HTTP エラー応答 (`status_codes` の 4xx/5xx) と、 php-ci3-unearth が記録した `errors[]` (例外/警告) を区別して整理
- error pattern が十分に観測されているか

## 7. 移行検討の論点

- pattern 間で挙動が大きく違う点 (例: 「pattern-1 は INSERT、 pattern-2 はエラー応答のみ」)
- 移行先 API 1 つに統合できるか、 分割が必要か、 の観点
- 未観測リスクが大きそうな箇所 (例: 「PUT/DELETE の観測はゼロ」)
- 移行レビューで追加観測したいシナリオ

# 出力の例 (構造のみ、 中身は入力に合わせて書く)

```markdown
# 仕様候補: POST /api/cart/add

## 1. エンドポイント識別

- HTTP method: POST
- path_pattern: /api/cart/add
- controller_path: application/controllers/api/Cart.php (action: add)
- 観測件数: 4 件、 error rate 0%

## 2. 入力仕様候補

| フィールド | 型 | 備考 |
|---|---|---|
| item_code | string | observed (根拠: pattern=pattern-1) |
| quantity | number | observed (根拠: pattern=pattern-1) |

(以下略)
```
````

## このプロンプトを使った後に

- 出力された仕様候補を、 必ず controller_path に書かれているソースコードファイルと突き合わせる
- `tables_confidence: best_effort` のテーブルは DDL で実在を確認する
- pattern 件数が少ない (例: 5 件未満) パターンは仕様としての断定を避け、 移行リハーサルで再観測する
- 複数 entrypoint を処理する場合は、 出力済みの仕様候補同士で table 利用が重複している箇所をまとめると、 domain 単位の整理に進めやすい
