# php-unearther

php-unearther is an experimental PHP library for observing runtime behavior in legacy API applications and generating migration-oriented behavior reports.

It is not an APM replacement. The goal is to help answer questions such as:

- Which SQL statements does this endpoint execute?
- Which tables does it read or write?
- Which execution patterns are actually observed in traffic?
- Which external HTTP calls are made?
- How close is a new implementation to the observed behavior of the legacy API?

The first implementation target is CodeIgniter3 on PHP 7.3. The core schema is intended to be reused later by a Laravel 11 adapter.

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

The observation record can include:

- HTTP method, path, status, duration, request shape, response shape
- Controller or route metadata when an adapter can provide it
- SQL operation, normalized statement hash, bind shape, tables, duration, caller
- External HTTP calls recorded through Guzzle middleware
- Errors recorded by adapters or wrappers

Values are represented as shapes where possible. For example, request fields are recorded as `string`, `number`, `boolean`, `array`, or nested structures rather than raw values.

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
    'sample_rate' => 0.01,
    'sink' => array(
        'type' => 'jsonl',
        'path' => APPPATH . 'logs/unearther-{date}.jsonl',
        'date_format' => 'Y-m-d',
    ),
)
```

For CodeIgniter3, pass this array through hook params. A future Laravel adapter can use the same keys from `config/unearther.php`.

## CodeIgniter3 Usage

Register the lifecycle hook in `application/config/hooks.php`.

```php
$hook['pre_system'][] = array(
    'class' => 'CoffeeR\\Unearther\\Adapter\\CodeIgniter3\\Hook',
    'function' => 'start',
    'filename' => '',
    'filepath' => '',
    'params' => array(array(
        'service' => 'legacy-api',
        'sample_rate' => 0.01,
        'sink' => array(
            'path' => APPPATH . 'logs/unearther-{date}.jsonl',
        ),
    )),
);

$hook['post_system'][] = array(
    'class' => 'CoffeeR\\Unearther\\Adapter\\CodeIgniter3\\Hook',
    'function' => 'finish',
    'filename' => '',
    'filepath' => '',
    'params' => array(),
);
```

Wrap the CodeIgniter DB object at an application bootstrap point you control.

```php
use CoffeeR\Unearther\Adapter\CodeIgniter3\Hook;
use CoffeeR\Unearther\Adapter\CodeIgniter3\ObservedDb;

$CI =& get_instance();
$CI->db = new ObservedDb($CI->db, Hook::collector());
```

The wrapper records calls made through `query()`. Other DB entry points may need additional wrapping depending on the application.

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

The report groups traces by endpoint and observed execution pattern. A pattern is currently based on SQL operation/table flow plus Guzzle external HTTP calls.

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
- SQL operation/table extraction
- JSONL writing
- endpoint aggregation
- execution pattern grouping
- Markdown rendering

## Built With Codex

This prototype was designed and implemented with help from OpenAI Codex.

## License

MIT
