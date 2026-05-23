#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../../.." && pwd)"
E2E_DIR="$ROOT_DIR/tests/e2e/codeigniter3-sqlite"
COMPOSE=(docker compose -f "$E2E_DIR/docker-compose.yml" -p php-unearth-ci3-sqlite)
BASE_URL="http://localhost:18080"

cleanup() {
  "${COMPOSE[@]}" down --remove-orphans >/dev/null 2>&1 || true
}
trap cleanup EXIT

"${COMPOSE[@]}" down --remove-orphans >/dev/null 2>&1 || true
"${COMPOSE[@]}" build
"${COMPOSE[@]}" up -d
"${COMPOSE[@]}" exec -T --user www-data app php scripts/init-db.php

for attempt in $(seq 1 60); do
  if curl -fsS "$BASE_URL/api/products?category_id=1" >/dev/null 2>&1; then
    break
  fi
  if [ "$attempt" -eq 60 ]; then
    "${COMPOSE[@]}" logs app fake-payment
    curl -i "$BASE_URL/api/products?category_id=1" || true
    echo "Timed out waiting for CI3 fixture" >&2
    exit 1
  fi
  sleep 1
done

"${COMPOSE[@]}" exec -T app sh -lc 'rm -f runtime/logs/*.jsonl runtime/e2e-export.jsonl runtime/e2e-report.json'

request() {
  local expected="$1"
  local method="$2"
  local path="$3"
  local body="${4:-}"
  local output
  local code

  output="$(mktemp)"
  if [ -n "$body" ]; then
    code="$(curl -sS -o "$output" -w '%{http_code}' -X "$method" "$BASE_URL$path" -H 'Content-Type: application/json' --data "$body")"
  else
    code="$(curl -sS -o "$output" -w '%{http_code}' -X "$method" "$BASE_URL$path")"
  fi

  if [ "$code" != "$expected" ]; then
    echo "Expected HTTP $expected for $method $path, got $code" >&2
    cat "$output" >&2
    rm -f "$output"
    exit 1
  fi
  rm -f "$output"
}

request 201 POST /api/users/register '{"name":"Alice Example","email":"alice@example.test","password":"password123"}'
request 422 POST /api/users/register '{"name":"Alice Example","email":"alice@example.test","password":"password123"}'
request 422 POST /api/users/register '{"name":"Bob Example","email":"bob@example.test","password":"short"}'
request 200 GET '/api/products?category_id=1'
request 200 GET /products/SKU-COFFEE
request 200 POST /api/orders/dry-run '{"user_id":1,"items":[{"product_code":"SKU-MUG","quantity":1}]}'
request 201 POST /api/orders '{"user_id":1,"items":[{"product_code":"SKU-COFFEE","quantity":1},{"product_code":"SKU-ESPRESSO","quantity":1}]}'
request 422 POST /api/orders '{"user_id":1,"items":[{"product_code":"NO-SUCH-SKU","quantity":1}]}'

"${COMPOSE[@]}" exec -T app sh -lc 'php vendor/bin/unearth export runtime/logs/*.jsonl --profile ai --format jsonl > runtime/e2e-export.jsonl'
"${COMPOSE[@]}" exec -T app sh -lc 'php vendor/bin/unearth report runtime/logs/*.jsonl --format json > runtime/e2e-report.json'
"${COMPOSE[@]}" exec -T app sh -lc 'find runtime application/logs -maxdepth 2 -type f -print'
"${COMPOSE[@]}" exec -T app php scripts/assert-traces.php
