# php-unearther

php-unearther is an experimental PHP library for observing runtime behavior in legacy API applications and generating migration-oriented behavior reports.

It is not an APM replacement. The goal is to help answer questions such as:

- Which SQL statements does this endpoint execute?
- Which tables does it read or write?
- Which execution patterns are actually observed in traffic?
- Which external HTTP calls are made?
- How close is a new implementation to the observed behavior of the legacy API?

The first implementation target is CodeIgniter3 on PHP 7.3. The core schema is intended to be reused later by a Laravel 11 adapter.

The core package supports PHP 7.3 and newer runtimes. It avoids PHP 8-only syntax so it can be installed into older CodeIgniter3 applications while still allowing PHP 8 projects to consume the same report tooling.

## Motivation

Legacy API migrations often start with incomplete or outdated specifications. Reading source code helps, but it does not always reveal which branches are actually used in production, which SQL patterns appear for each endpoint, or which external services are involved.

php-unearther is meant to capture observed facts from sampled requests and turn them into a report that is useful during migration work. The report is intentionally behavior-oriented: it shows endpoint shapes, SQL/table flow, external HTTP calls, and recurring execution patterns.

## Status

This package is an early prototype.

Current focus:

- CodeIgniter3-oriented instrumentation
- JSONL observation logs
- SQL/table observation
- Guzzle-based external HTTP observation
- Markdown and JSON behavior reports

Not included in the initial scope:

- Session diff tracking
- Raw request/response capture by default
- Masking policy engine
- curl interception
- PHP extension hooks
- DB before/after snapshots
- Full OpenAPI generation

## Installation

Until the package is published to Packagist, install it from GitHub as a Composer VCS repository.

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

Then run:

```bash
composer update coffee-r/php-unearther
```

## Observation Model

php-unearther writes one JSON object per sampled request.

Each observation includes a `schema_version` field. The current schema version is `1`.

The observation record can include:

- HTTP method, path, status, duration, request shape, response shape
- Controller or route metadata when an adapter can provide it
- SQL operation, normalized statement hash, bind shape, tables, duration, caller
- External HTTP calls recorded through Guzzle middleware
- Errors recorded by adapters or wrappers

Values are represented as shapes where possible. For example, request fields are recorded as `string`, `number`, `boolean`, `array`, or nested structures rather than raw values.

The observation schema includes a `calls` array for future controller/model/library call trace events. The current CodeIgniter3 adapter does not automatically populate it; SQL caller metadata and Guzzle external HTTP events are the supported call-site signals in this prototype.

## Production Data Safety

Be careful when enabling php-unearther in production or production-like environments. Observation logs may still reveal sensitive information even when raw request and response values are not stored.

Before enabling it, review:

- Whether endpoint paths, query keys, request keys, response keys, SQL text, bind shapes, table names, caller paths, or external hostnames expose personal information, credentials, tenant identifiers, or internal system details
- Whether SQL statements contain literal values because an application builds SQL strings without bind parameters
- Whether log files are written to a restricted location with appropriate filesystem permissions
- Whether log retention, backup, transfer, and deletion policies match the sensitivity of the application
- Whether sample rates are low enough for production traffic and can be disabled quickly
- Whether reports generated from the logs are treated with the same care as the raw JSONL logs

The initial prototype does not include a masking policy engine. If your application may place secrets, tokens, emails, names, addresses, phone numbers, payment identifiers, or customer identifiers in observed fields, add application-side filtering or keep php-unearther disabled until the capture surface is understood.

## Configuration

Adapters may load configuration differently, but the package normalizes them into the same options.

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

For CodeIgniter3, pass this array through hook params. A future Laravel adapter can use the same keys from `config/unearther.php`.

`codeigniter3.sql_capture` can be `query_history`, `observed_db`, or `none`. The older `codeigniter3.capture_query_history` key is still accepted as a compatibility alias.

`sql.capture_text` is off by default. When set to `true`, SQL events include the raw SQL string, normalized SQL, and literal-insensitive fingerprint SQL. This is useful for migration analysis, but it can expose sensitive values when an application builds SQL with literals instead of bind parameters.

`failure_mode` controls what happens when php-unearther itself fails while observing a request. The default is `throw` so installation or configuration problems are visible during rollout. Set it to `log` in production if the application must continue even when observation fails. In `log` mode, CodeIgniter3 uses `log_message('error', ...)` when available and falls back to `error_log()`.

`http.endpoint_patterns` is optional. When a request matches a configured Laravel-style path pattern such as `/api/users/{id}`, reports group it by that canonical endpoint path. php-unearther does not infer path parameters automatically; unconfigured paths are grouped by the raw request path.

## CodeIgniter3 Usage

The current CodeIgniter3 adapter assumes a classic PHP request lifecycle such as PHP-FPM, CGI, or Apache mod_php. Long-lived worker runtimes such as Swoole, RoadRunner, and ReactPHP are not supported by this prototype because the hook keeps request observation state in static process-local fields.

CodeIgniter3 does not natively assume namespaced Composer classes in hook definitions. The safest setup is to create a small bridge hook inside the application and let that bridge call php-unearther.

Create `application/hooks/UneartherHook.php`:

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

Then register the bridge in `application/config/hooks.php`.

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

### CodeIgniter3 SQL Capture

By default, the CodeIgniter3 hook uses `sql_capture => query_history` and reads `$CI->db->queries` and `$CI->db->query_times` at the end of sampled requests. This is intentionally used as the first capture strategy because it can observe SQL produced by Query Builder calls such as `where()`, `get()`, `insert()`, and direct `query()` calls, as long as CodeIgniter's `save_queries` setting is enabled.

This strategy has tradeoffs:

- It depends on CodeIgniter query history being available
- It does not provide precise caller file/line information
- It may increase memory usage if many queries are executed in one request

You can disable SQL capture:

```php
'codeigniter3' => array(
    'sql_capture' => 'none',
)
```

An experimental DB wrapper is also available for application bootstrap points you control.

```php
use CoffeeR\Unearther\Adapter\CodeIgniter3\Hook;
use CoffeeR\Unearther\Adapter\CodeIgniter3\ObservedDb;
use CoffeeR\Unearther\Sql\SqlAnalyzer;

$CI =& get_instance();
$CI->db = new ObservedDb($CI->db, Hook::collector());
```

To include SQL text when using `ObservedDb`, pass an analyzer with text capture enabled:

```php
$CI->db = new ObservedDb($CI->db, Hook::collector(), new SqlAnalyzer(true));
```

When using `ObservedDb`, set `sql_capture => observed_db` so the hook does not also record CodeIgniter query history.

```php
'codeigniter3' => array(
    'sql_capture' => 'observed_db',
)
```

The wrapper records calls made through `query()`, but it is not a complete Query Builder interception strategy. In many CI3 applications, query history capture is the more practical baseline.

### HTTP Shape Capture

For sampled requests, the CodeIgniter3 hook records `query_shape` and `request_shape`. If the request content type is `application/json` or a structured `+json` type, php-unearther decodes the body and records the JSON shape. If the body is invalid JSON, too large, or not JSON, it falls back to `$_POST` shape.

JSON request shape capture reads `php://input`. On supported PHP runtimes this is normally reusable, but if application bootstrap code consumes or replaces the raw input before the hook runs, php-unearther may not be able to see the JSON body and will fall back to `$_POST`.

Response body shape capture is off by default. Enable it only after reviewing the response surface:

```php
'http' => array(
    'capture_json_response_shape' => true,
    'max_body_bytes' => 65536,
)
```

When enabled, the CodeIgniter3 hook reads `$CI->output->get_output()` for sampled responses with a JSON content type and records only the response shape.

## Trace IDs

Each sampled request receives a generated trace ID when observation starts. The ID contains a UTC timestamp prefix and a 128-bit random suffix:

```text
20260601T101122-3f4e3f8b9c0d4d67a1b2c3d4e5f60718
```

This keeps IDs sortable by start time while making collisions practically unlikely for normal sampling volumes.

## Log Rotation

JSONL sinks support daily file rotation through a `{date}` placeholder in the path.

```php
'sink_path' => APPPATH . 'logs/unearther-{date}.jsonl'
```

This writes files such as:

```text
unearther-2026-06-01.jsonl
unearther-2026-06-02.jsonl
```

If the path does not include `{date}`, php-unearther writes to the exact path as-is.

## Guzzle Usage

Attach the middleware to a Guzzle handler stack.

```php
use CoffeeR\Unearther\Guzzle\UneartherMiddleware;
use GuzzleHttp\HandlerStack;

$stack = HandlerStack::create();
$stack->push(UneartherMiddleware::create($collector));
```

External HTTP calls made through that client will be added to the current trace.

## Reports

Generate a Markdown behavior report:

```bash
vendor/bin/unearth report application/logs/unearther.jsonl --format md
```

Generate a machine-readable aggregate report:

```bash
vendor/bin/unearth report application/logs/unearther.jsonl --format json
```

The report groups traces by endpoint and observed execution pattern. A pattern is currently based on SQL operation/table/hash flow plus Guzzle external HTTP calls. Pattern granularity is fixed in this prototype and is not configurable.

If `http.endpoint_patterns` is configured during capture, reports prefer the recorded canonical endpoint path. Reports also include endpoint-level error counts and grouped error summaries from trace `errors`.

Aggregate reports and Markdown reports do not render raw SQL text. They include statement hashes and fingerprint SQL examples when those fields were captured.

The CLI currently supports report generation only. It does not include a `compare` command or `--from` / `--to` date filtering.

Example pattern:

```text
SELECT M_SHOHIN -> INSERT T_CART
```

## Development

```bash
composer install
vendor/bin/phpunit
php bin/unearth report tests/Fixtures/jsonl/cart_add.jsonl --format md
```

The test suite is fixture-driven and focuses on deterministic behavior:

- shape extraction
- JSON request and response shape extraction
- SQL operation/table extraction
- endpoint pattern normalization
- CodeIgniter3 hook lifecycle behavior
- JSONL writing
- CLI warning behavior
- endpoint aggregation
- error aggregation
- execution pattern grouping
- Markdown rendering

## Built With Codex

This prototype was designed and implemented with help from OpenAI Codex.

## License

MIT
