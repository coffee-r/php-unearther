# Observation Schema v1

`observation-v1.schema.json` describes one JSON object per JSONL line.

The schema is intentionally language-neutral: CodeIgniter3, Laravel, or another
runtime can emit the same top-level fields and compare behavior outside this
package.

## Value Classes

- `*_shape` fields describe structure and scalar types only.
- `*_tokens` and `*_tokenized` fields preserve equality without exposing raw
  values. They are emitted only when `redaction.secret` is configured.
- `*_raw`, `statement_text`, and `bind_raw` are raw/concrete values. They are
  `null` by default and removed by `unearth export --profile ai`.

## SQL Confidence

SQL analysis is regex-based in v1. `statement_normalized` and
`statement_hash` are suitable for grouping. `tables` is best-effort and may be
`unknown` for complex CTEs, subqueries, vendor syntax, or dynamic SQL.

CodeIgniter3 sampled query-history capture records final SQL strings from the
DB object. It has broad coverage, but does not provide precise caller or bind
metadata; those limitations are surfaced in `sql[].analysis.warnings`.

## Production Validation

The schema is a test/CI contract. Production requests are not validated against
JSON Schema at runtime so observation overhead stays low.
