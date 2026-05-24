# LLM プロンプトパック

php-ci3-unearth が出す観測ログから、 生成AI に「観測された 1 つの入口の日本語仕様候補」を起こさせるためのテンプレート集。 仕様の最終確定は人間の責務だが、 ログ → 仕様候補の最初のドラフトを安定して作るための雛形を提供する。

## 含まれるテンプレート

| テンプレート | 用途 |
|---|---|
| [`entrypoint-spec-candidate.md`](./entrypoint-spec-candidate.md) | observed entrypoint 1 件分の JSON を入力に、 移行検討用の日本語仕様候補を起こす |

## ワークフロー

```
[CI3 アプリ実行]
    ↓ (php-ci3-unearth hook が JSONL 観測ログを書き出す)
logs/unearth-*.jsonl              ← raw 値を含む可能性あり (capture_text 等が on の場合)
    ↓ (unearth export --profile ai で raw を剥がす)
redacted.jsonl                    ← *_raw / statement_text / bind_raw が落ちた AI 投入安全な中間
    ↓ (unearth report --format json で entrypoint 単位に Aggregate)
report.json
    ↓ (jq などで observed_entrypoints[i] を 1 件抜く)
entrypoint.json
    ↓ (entrypoint-spec-candidate.md テンプレートに差し込んで LLM へ)
仕様候補 Markdown (LLM 出力)
    ↓ (人間がレビュー、 source code / DB DDL と突き合わせ)
仕様書ドラフト
```

## 推奨入力フォーマット

**raw 値を剥がした中間 JSONL を `export --profile ai` で作り、 そこから `report --format json` を回す** のが推奨。 直接 raw logs に `report` を回すと、 default の `--value-mode normalized` では raw を出さないが、 「AI 投入向けの公式 redacted 経路」は `export --profile ai` なので、 まずそこを通すのが安全側。

```bash
# Step 1: raw を剥がした AI 投入安全な中間 JSONL を作る
vendor/bin/unearth export logs/unearth-*.jsonl --profile ai --format jsonl > redacted.jsonl

# Step 2: entrypoint 単位に集計
vendor/bin/unearth report redacted.jsonl --format json > report.json

# Step 3: 1 entrypoint 抜き取り (推奨: カバレッジ判断用の root フィールドも一緒に)
jq '{
  sample_rates,
  observed_started_at_min,
  observed_started_at_max,
  entrypoint: .observed_entrypoints[0]
}' report.json > entrypoint.json

# シンプル版 (entrypoint のみ。 sample_rate / 観測期間は LLM に「不明」と評価させる)
jq '.observed_entrypoints[0]' report.json > entrypoint.json
```

複数 entrypoint をループで処理する場合は、 `.observed_entrypoints | length` で件数を取って index を回す。

入力候補が複数あるが、 上記を推奨する理由:

- **`report --format json` (redacted intermediate 経由)**: 既に observed entrypoint 単位 / execution pattern 単位に集約済み。 status 分布、 SQL flow、 外部 API 呼び出し、 代表 trace まで揃っている。 LLM に渡す情報密度が最も高い。
- **`report --format md` (markdown)**: 人間可読の whitespace で token を食う割に、 構造情報は JSON と等価。 LLM 入力には不向き。
- **`export --profile ai --format jsonl` (生のまま)**: 1 trace ごとの redacted 観測。 LLM 側に集約させる必要があり、 件数が増えると不安定になる。 個別 trace を深掘りしたいときの 2 次入力として使う。
- **`report` を raw logs に直接当てる**: default `--value-mode normalized` であれば raw 値はレンダリングされないが、 「AI 投入用の redacted コマンド」は project 上 `export --profile ai`。 中間 redacted JSONL を経由する pipeline の方が、 raw mode への将来変更や設定ミスに対して防御的。

複数 entrypoint を一括処理したい場合は、 シェルループで 1 件ずつテンプレートに差し込んで投げる方が、 1 リクエストに全部入れるより出力品質が安定する。

## 安全要件

LLM (特にクラウド LLM) に渡す前に必ず確認すること。

- **必ず redacted 済みのデータを渡す**。 `unearth report` や `unearth export --profile ai` の出力は raw 値 (`*_raw`, `statement_text`, `bind_raw`) を落としているが、 raw JSONL ログを直接 LLM に貼らないこと。
- **`tables`、 `path_pattern`、 controller path、 外部ホスト名は通常残る**。 これらが組織機密に該当する場合は、 入力段階で更にフィルタする。
- **生成された仕様候補は必ずソースコードと DB DDL に突き合わせる**。 LLM は観測されていない分岐を埋めようとする傾向があるため、 テンプレートで明示的に禁止するが、 抜けがないか人間が確認する。
- **`tables_confidence: best_effort` の table 一覧は補助情報**。 SQL 解析は v1 では regex ベースで完全ではない。 仕様に取り込む前に DDL や `controller_path` を見て裏取りする。
- **`observed_count`, `sample_rate`, `error_rate` を必ず添えて LLM に判断させる**。 サンプリングされなかった分岐は仕様候補にしないというガードを LLM 側にも持たせる。

## 関連ドキュメント

- 観測ログの各フィールド意味: [`docs/schema/README.md`](../schema/README.md)
- JSON Schema 定義: [`docs/schema/observation-v1.schema.json`](../schema/observation-v1.schema.json)
- 全体設計と境界: プロジェクトルートの `README.md`
