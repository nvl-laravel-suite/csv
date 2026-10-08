# NVL CSV — API and usage

## Quickstart

```sh
composer require nvl/csv:^5.0
php artisan nvl:install csv --dry-run
php artisan nvl:install csv
```

Required NVL dependencies: `nvl/core` (`^5.0`). Select a writable configured disk and trusted export columns. Queue tenant work through registered handlers; do not serialize request services or tenant context into jobs.
Review the published common config, select one migration owner, and run schema preflight before existing-table upgrades. The installer does not enable features or run migrations. Follow the detailed installation and capability sections below before invoking a storage/provider operation.

Inject `Nvl\Csv\Contracts\CSVExportContract` in a host service. After supplying the trusted inputs described above, the first public call is:

```php
use Nvl\Csv\Contracts\CSVExportContract;

/** @var CSVExportContract $capability */
$result = $capability->disk('local')->fromArray([['id' => 1]]);
```

Use the [event catalog](docs/events.md) and [Testing your app](#testing-your-app) below. The suite [getting-started guide](https://github.com/nvl-laravel-suite/laravel-suite/blob/main/docs/getting-started.md) provides a complete Comments host fixture; package archives retain their own local references.


[← NVL Laravel Suite](https://github.com/nvl-laravel-suite)

For support, [open an issue](https://github.com/nvl-laravel-suite/csv/issues). For vulnerabilities, use
[private reporting](https://github.com/nvl-laravel-suite/csv/security/advisories/new). See [Contributing](CONTRIBUTING.md).

See the [installation and publishing guide](https://github.com/nvl-laravel-suite/laravel-suite/blob/main/docs/installation.md) for Composer setup, configuration, migration ownership, and agent skills.

## Quick reference

| Item | Value |
|---|---|
| Installed through | `composer require nvl/csv:^5.0` |
| Module identifier | `nvl/csv` |
| PHP namespace | `Nvl\Csv` |
| Service provider | `Nvl\Csv\Providers\CsvServiceProvider` |
| Configuration | None; behavior is supplied through typed options and services |

Typed, memory-conscious CSV analysis, validation, transformation, import, export, and queued chunk processing for Laravel 12–13.

## Purpose

`nvl/csv` turns CSV handling into an explicit application boundary. It provides immutable dialect configuration, Spatie Data option and result objects, field mappings, reusable validators and transformers, streaming filesystem support, synchronous batches, and Laravel queue batches. The package is headless: it owns no routes, controllers, models, tables, or application-specific persistence.

The public namespace is `Nvl\Csv`. Its fluent import/export surface is compatible with the original `App\Lib\CSV` library while correcting its operational weaknesses: options are applied consistently, analyzers can be reused safely, BOM lengths and declared encodings are honored, remote Laravel disks are read as streams, exports are written as streams, duplicate policy is explicit, callback-bearing jobs serialize safely, and queued source chunks are staged instead of embedding the complete file in the queue payload.

## Requirements and installation

- PHP 8.4 or newer
- Laravel 12–13
- `ext-filter`, `ext-iconv`, `ext-json`, and `ext-mbstring`
- `nvl/core:^5.0`

Install with Composer:

```bash
composer require nvl/csv:^5.0
```

Laravel discovers `Nvl\Csv\Providers\CsvServiceProvider` automatically. There is no package configuration or migration to publish for synchronous analysis, import, or export.

Queued processing uses Laravel job batching. The consuming application must configure a queue connection and create the batch repository table if it does not already exist:

```bash
php artisan make:queue-batches-table
php artisan migrate
```

Keep the queue connection’s `retry_after` value above the job timeout of 300 seconds. Async jobs resolve `nvl-csv.queue.connection` and `nvl-csv.queue.name` first, then Core queue defaults, then the selected Laravel connection and queue. Explicit `sync` is preserved. Jobs stage bounded JSON chunks on the application’s `local` filesystem disk until the job completes, is cancelled, or exhausts its retries.

## Analyze a file

```php
use Nvl\Csv\Contracts\CSVAnalyzerContract;

$analysis = (new CSVAnalyzerService())->analyzeFile($absolutePath);

$analysis->detectedDelimiter;
$analysis->detectedEncoding;
$analysis->headers;
$analysis->columnAnalysis;
$analysis->issues;
$analysis->recommendations;
```

Use `analyzeFromDisk($disk, $path)` for a Laravel filesystem disk or `quickAnalyze($absolutePath)` for a small structural preview. Full analysis samples at most 100 rows for type/statistical inference and scans at most 10,000 data rows before estimating the total. The analyzer assumes the first parsed row is a header row.

Encoding and delimiter detection are recommendations, not proof. Ambiguous legacy encodings and CSV dialects should be confirmed by the caller before an irreversible import.

## Import

```php
use Nvl\Csv\Enums\CSVTypeEnum;
use Nvl\Csv\Contracts\CSVImportContract;
use Nvl\Csv\ValueObjects\CSVFieldMapping;

$result = CSVImport::make()
    ->fromDisk('imports', 'incoming/people.csv')
    ->mapField(
        'age',
        'age',
        CSVFieldMapping::typed('age', 'age', CSVTypeEnum::INTEGER, required: true),
    )
    ->mapField(
        'name',
        'display_name',
        CSVFieldMapping::withTransformer(
            'name',
            'display_name',
            static fn (mixed $value): string => trim((string) $value),
        ),
    )
    ->processRow(static function (array $row, int $rowNumber): void {
        // Call an application Action or perform the intended persistence write.
    })
    ->import();
```

`fromFile()` accepts an absolute local path. `fromDisk()` reads a Laravel disk stream and does not require the adapter to expose a local path. With no field mappings, rows are returned as header-keyed associative arrays.

`import()` returns `CSVImportResult`. Imports use a database transaction by default. A result containing row failures causes that top-level transaction to roll back. The transaction owns the default Laravel database connection unless `onConnection('connection-name')` selects another configured connection. Writes performed by callbacks must use that same connection for the import's atomicity guarantee to apply. Use `withTransaction(false)` when successful rows should be committed independently, or use `stopOnError()` when the first threshold-matching error must stop processing.

For pull-based processing:

```php
foreach (CSVImport::make()->fromFile($path)->stream() as $rowNumber => $row) {
    // Streaming does not create an enclosing database transaction.
}
```

For bounded synchronous writes:

```php
$result = CSVImport::make()
    ->fromFile($path)
    ->onConnection('tenant')
    ->batch(500, static function (array $rows, int $batchNumber): void {
        // Each callback is wrapped in its own transaction by default.
    });
```

Configure `CSVImportOptionsData` when the options cross an HTTP, command, or job boundary:

```php
use Nvl\Csv\Data\CSVImportOptionsData;
use Nvl\Csv\Enums\CSVDelimiterEnum;
use Nvl\Csv\Enums\CSVEncodingEnum;
use Nvl\Csv\Enums\CSVTypeEnum;

$options = CSVImportOptionsData::from([
    'filePath' => $path,
    'delimiter' => CSVDelimiterEnum::SEMICOLON,
    'encoding' => CSVEncodingEnum::WINDOWS_1252,
    'skipRows' => 2,
    'limitRows' => 10_000,
    'hasHeaders' => true,
    'skipEmptyRows' => true,
    'strictMode' => true,
    'columnMapping' => ['external_id' => 'id'],
    'columnTypes' => ['external_id' => CSVTypeEnum::INTEGER],
    'metadata' => ['source' => 'partner-feed'],
]);

$result = CSVImport::make()
    ->withOptions($options)
    ->fromFile($path)
    ->import();
```

`columnMapping` accepts source-to-target field names or `CSVFieldMapping` objects. `columnTypes` accepts `CSVTypeEnum` instances or enum values and augments those mappings with validation and casting. Result metadata includes caller-provided DTO metadata plus operational fields.

`CSVConfiguration(includeHeaders: false)` produces `col_0`, `col_1`, and subsequent generated names for headerless input. Its first row is retained on non-seekable and decoded streams. Header names must be non-empty and unique. In lenient mode, short rows are padded and long rows are truncated to the known column count. Strict mode records a `CSVParseException` for uneven rows instead. Failed-row payloads and error strings are retained for the first 1,000 failures; counters remain exact and the result contains a warning when further diagnostic details are omitted.

## Duplicate handling

Call `detectDuplicates($field)` or set `unique: true` on a `CSVFieldMapping` to compare transformed values within the current source file.

- `SKIP` omits subsequent duplicate rows and increments `skippedRows`.
- `ERROR` records a validation failure for the duplicate row.
- `CREATE` accepts every row.
- `UPDATE`, `REPLACE`, `MERGE`, `INCREMENT`, and `ARCHIVE` pass the duplicate row to the application row processor. Their persistence meaning belongs to the consumer because the package does not know the target model, lookup key, archive schema, or write Action.

This duplicate index is operation-local and strict-type-sensitive. It does not replace a database unique constraint or concurrency-safe upsert.

## Export

```php
use Nvl\Csv\Contracts\CSVExportContract;
use Nvl\Csv\ValueObjects\CSVConfiguration;

$result = CSVExport::make()
    ->configure(CSVConfiguration::excel()->withIncludeIndex())
    ->disk('exports')
    ->path('reports')
    ->filename('people.csv')
    ->headings(['Name', 'Email'])
    ->fields(['name', 'email'])
    ->fromQuery($peopleQuery);
```

Export sources are:

- `fromArray(array $rows)`
- `fromCollection(Collection $rows)`
- `fromQuery(Builder $query)`, which always reads in bounded chunks
- `stream(Closure $provider)`, where the provider receives a writer callback

Fields may be dot-notated array keys or closures receiving the complete row. When fields and headings are omitted, the first row’s keys define both for the complete export. Later rows follow that field order; missing keys become empty fields and extra keys are ignored. Reusing the exporter infers a fresh set of fields. Closure-based fields require explicit headings. Arrays and ordinary objects are JSON encoded, `DateTimeInterface` values use ISO 8601, backed enums use their values, and `Stringable` objects use their string representation. Booleans become `1` or `0`, and null becomes an empty field.

`fromQuery()` is generic over the concrete Eloquent model. A correctly typed
`Builder<App\Models\User>` may be passed directly under maximum-level PHPStan;
consumers do not need to widen it to `Builder<Model>` or erase its model type.

`CSVExportOptionsData` applies format, delimiter, enclosure, escape, BOM, headers, index, processing mode, chunk size, encoding, and memory settings:

```php
use Nvl\Csv\Data\CSVExportOptionsData;
use Nvl\Csv\Enums\CSVEncodingEnum;
use Nvl\Csv\Enums\CSVExportFormatEnum;

$options = CSVExportOptionsData::from([
    'disk' => 'exports',
    'path' => 'reports',
    'filename' => 'people.csv',
    'format' => CSVExportFormatEnum::RFC4180,
    'encoding' => CSVEncodingEnum::UTF8_BOM,
    'headings' => ['Name', 'Email'],
    'fields' => ['name', 'email'],
]);

$result = CSVExport::make()->withOptions($options)->fromArray($rows);
```

Fluent builder methods initialize an `exports` directory by default. A DTO created directly without `path` writes at the disk root. `CSVExportResult::path` is an absolute path when the adapter supports `path()` and otherwise contains the storage key. The result metadata always includes `storage_path`, and `fileExists()` uses the configured disk.

Defaults, format presets, and the analyzer use standard doubled enclosure characters with `escape: ''`. This preserves embedded quotes and trailing backslashes. To read a legacy file that deliberately uses PHP’s backslash escape convention, configure `new CSVConfiguration(escape: '\\')` or provide that explicit escape in the import DTO. Explicit custom escape settings remain supported. See the [PHP CSV escaping contract](https://www.php.net/manual/en/function.fputcsv.php).

The package does not neutralize spreadsheet formulas. If untrusted fields will be opened in Excel or similar software, the application must apply its chosen CSV/formula-injection policy before export.

## Asynchronous processing

```php
use Illuminate\Bus\Batch;
use Nvl\Csv\Contracts\CSVAsyncProcessorContract;

$batch = CSVAsyncProcessor::make()
    ->fromFile($path)
    ->withOptions($options)
    ->withChunkSize(1_000)
    ->mapField('email', 'email')
    ->processRow(static function (array $row, int $rowNumber): void {
        // Execute idempotent application work.
    })
    ->onBatchComplete(static function (int $chunk, int $processed, array $errors): void {
        // Per-job summary.
    })
    ->onProgress(static function (Batch $batch): void {
        // Laravel batch progress callback.
    })
    ->onComplete(static function (Batch $batch): void {
        // Laravel batch finally callback.
    })
    ->processAsync();
```

`processAsyncWithTracking()` returns a batch ID and stores operational metadata under `csv_batch_metadata` on the local disk. Read it with `getBatchStatus($id)` and cancel work with `cancelBatch($id)`.

Callbacks and mapping closures are wrapped for Laravel serialization, but everything they capture must still be serializable. Do not capture open resources, active HTTP requests, service containers, or non-serializable third-party clients. Row work should be idempotent because queue retries can execute it again. Laravel batch callbacks run in the queue environment and must not use `$this`. Row-level exceptions are reported to `onBatchComplete` and do not fail the containing job; infrastructure or callback failures still use Laravel’s normal retry and failed-job behavior.

## Validators, transformers, filters, and value objects

The compatibility surface includes:

- `CSVFieldValidator` and `CSVRowValidator`
- `StringTransformer`, `NumericTransformer`, `DateTransformer`, `ChainedTransformer`, and `ConditionalTransformer`
- `CSVFilter::field()`, `custom()`, `all()`, and `any()`
- `CSVConfiguration`, `CSVFieldMapping`, `CSVImportResult`, and `CSVExportResult`
- typed enums for delimiter, encoding, export format, processing mode, field type, quality, error level, operation status, duplicate strategy, and notification channel
- `CSVAnalysisResultData`, `CSVImportOptionsData`, `CSVExportOptionsData`, and `CSVProgressData`

The Data objects are registered with Core's Data provider for generated TypeScript discovery.

## Agent guidance

Publish the bundled Laravel Boost skill when the consumer wants repository-local guidance:

```bash
php artisan vendor:publish --tag=nvl-csv-translations
php artisan vendor:publish --tag=nvl-csv-skills
```

This publishes `nvl-csv` into the application’s `.agents/skills` directory.

## Security and operational boundaries

Treat CSV input as untrusted. Enforce upload size, accepted MIME/extension policy, authorization, virus scanning, retention, and storage visibility before handing a source to this package. Define required mappings and validators before persistence. Keep error payloads away from public responses when source rows contain personal or confidential values.

The analyzer samples data and the import result retains failed-row payloads, so consumers processing sensitive or extremely error-prone files should bound source size and error tolerance. Queue batch metadata is operational convenience data rather than durable business state.

## Tenant queued imports

Tenant async imports require a class-resolved `CSVRowHandler`; callback-backed
row, progress, batch, completion, and field transformations are rejected before
staging. Jobs carry scalar work references plus a captured tenant envelope and
verify the private manifest and chunk checksums after context restoration.

## Development and verification

From a standalone checkout of the public CSV repository:

```bash
composer install
composer quality
composer validate --strict
```

Local Dagger verification checks package-family contracts, dependency declarations and maximum PHPStan strictness. The 90% changed-line coverage policy requires a separate local coverage run with a coverage-capable PHP runtime; the ordinary Dagger release gate does not collect coverage.

## Supported PHP usage

The source `@api` declarations identify supported workflows, extension contracts, and value types. Public members marked `@internal` and untagged implementation types remain package-owned. Concrete Actions retain their existing constructors, qualifiers, and `execute()` signatures.

A package model returned or accepted by a public workflow is an identity/result handle. Use its declared type and `getKey()`, `getKeyName()`, `getMorphClass()`, `getRouteKey()`, `getRouteKeyName()`, `is()`, `isNot()`, and `relationLoaded()`. Read only explicitly declared in-memory `@nvl-consumer-read` fields; ordinary model PHPDocs and fillable attributes do not grant consumer reads. Obtain display projections through public reads. Persistence, additional model queries, relation access/loading, and generic model serialization are outside this contract. Host-model queries remain available, while traversal or aggregates of package capability relations require the package public reader or authorized adapter.

## Testing your app

Inject `CSVAnalyzerContract`, `CSVImportContract`, `CSVExportContract`, or
`CSVAsyncProcessorContract` into application orchestration. All four defaults are
transient: resolve a fresh builder for each independent operation. Static
`CSVImport::make()`, `CSVExport::make()`, and `CSVAsyncProcessor::make()` remain
available for direct native operations; static factories are not interface
members and bypass a host interface replacement.

```php
use Nvl\Csv\Contracts\CSVImportContract;
use Nvl\Csv\ValueObjects\CSVImportResult;

final readonly class ImportContacts
{
    public function __construct(private CSVImportContract $imports) {}

    public function run(string $path): CSVImportResult
    {
        return $this->imports->fromFile($path)->import();
    }
}

$result = new CSVImportResult(1, 1, 1, 0, 0);
$imports = Mockery::mock(CSVImportContract::class);
$imports->shouldReceive('fromFile')->once()->with('/fixtures/contacts.csv')->andReturnSelf();
$imports->shouldReceive('import')->once()->withNoArgs()->andReturn($result);
$this->app->instance(CSVImportContract::class, $imports);
$actual = $this->app->make(ImportContacts::class)->run('/fixtures/contacts.csv');
expect($actual)->toBe($result);
```

CSV has no Eloquent models or package factories. Construct analysis/configuration
DTOs and result objects directly. For an export fixture, use the native
`CSVExportResult` constructor with explicit path, URL, filename, row/column
counts, file size, processing time, and Carbon creation time. Avoid
`fromExport()` and `fileExists()` in a database/file-free orchestration test.
Use `Storage::fake()` for real disk behavior and `Bus::fake()`/`Queue::fake()` for
host dispatch assertions. A substituted async processor proves the host call;
real manifest, tenant-handler, batching, and worker behavior need integration
tests. The container default supplies all four native async dependencies;
retain that wiring when extending the implementation. A test `instance()` is
an intentional fixed substitute; production mutable builders must not be
registered as singletons or retained across independent operations.

In Laravel application tests, register a native Mockery interface mock or a small
implementation with `$this->app->instance(Contract::class, $substitute)` before
resolving your application service. A host binding installed before package
registration is retained; later contract replacements affect subsequent
resolutions. Rebuild previously resolved host services after replacing their
dependencies. Concrete implementations remain callable with their original
constructors through major 5. Mocks exercise your application orchestration;
package authorization, persistence, and external effects need real integration
tests.

The four CSV service interfaces copy the native public instance methods (3 analyzer, 22 import, 12 export, 13 async). Mutable defaults remain transient; static factories and direct constructors remain callable.

For static consumer checks, include the shipped
[`consumer-audit.neon`](https://github.com/nvl-laravel-suite/core/blob/main/support/consumer-audit.neon) from
`vendor/nvl/core/support/consumer-audit.neon` in your host PHPStan configuration
and configure explicit `nvlConsumer.testPaths` for factory-backed tests. The
extension checks supported APIs and model/query boundaries; it does not prove
authorization or arbitrary dynamic SQL.

## Testing your app

Inject the supported contract rather than constructing its concrete Action or querying package tables. Replace `Nvl\Csv\Contracts\CSVExportContract` in Laravel's native container for a host-workflow test:

```php
use Nvl\Csv\Contracts\CSVExportContract;

$double = Mockery::mock(CSVExportContract::class);
$this->app->instance(CSVExportContract::class, $double);
// Configure the exact fromArray arguments and documented return value for your host case.
```

The package's conditional native binding preserves host substitutions. Production uses the real contract; test doubles do not prove its storage/authorization behavior.

This package has no persistent fixture model in the supported factory inventory. Test value objects and contract inputs directly; do not invent a package model factory.

Use Laravel `Event::fake()`, `Queue::fake()`, `Mail::fake()` or `Storage::fake()` only for the effects the host test intends to isolate. Use real commits/listeners for timing proof. Add the optional Core consumer boundary rules to host PHPStan:

```neon
includes:
    - vendor/nvl/core/support/consumer-audit.neon
parameters:
    nvlConsumer:
        testPaths: [tests]
        tableNames: []
        exceptions: []
```

Rules read installed public metadata without suite boot. They flag internal symbols, package model queries/writes, capability relations and owned tables; they cannot prove dynamic code or runtime authorization. Exact exceptions require `file`, `identifier`, `symbol`, and a documented `reason`. The published 5.x family is verified through the local Dagger release gate on PHP 8.4/Laravel 13, including owning suites, MySQL/PostgreSQL persistence contracts and sealed Tenancy consumers. Fresh public Composer installation, discovery and configuration/route caching are verified. PHP 8.5, Laravel 12, MariaDB and the full independent archive matrix require separate evidence. See the [verification and release policy](https://github.com/nvl-laravel-suite/laravel-suite#verification-and-releases).

## Error codes and events

All recognized package failures implement `Nvl\Support\Contracts\PackageException`; only `RespondableException` opts into safe response metadata. Keep native PHP programmer errors and Laravel/SDK exceptions distinct. The optional `PackageExceptionRenderer` is registered by the host in `withExceptions`; it leaves unrelated, marker-only and non-JSON handling to the host. Its JSON envelope is `{message:string, code:string, context:object}`. Request locale is host-owned; diagnostics/previous exceptions are not public copy. Event schemas and source connections are documented in [events](docs/events.md).

The table lists enum discriminators, including any successful codes retained for compatibility. A code is not itself an HTTP status; the throwing exception's `suggestedStatus()` is authoritative, especially legacy/custom constructors. Empty context renders as `{}`; only documented JSON-safe context is presented.

| Code | Suggested status | Public context | Translation key |
| --- | --- | --- | --- |
| `operation_failed` | Exception-defined; see `suggestedStatus()` | Declared safe scalar/array map; otherwise `{}` | `nvl-csv::responsecode.operation_failed` |
| `invalid_configuration` | 500 | {} | `nvl-csv::responsecode.invalid_configuration` |
| `memory_limit_exceeded` | 500 | Declared safe scalar/array map; otherwise `{}` | `nvl-csv::responsecode.memory_limit_exceeded` |
| `file_not_found` | 404 | Declared safe scalar/array map; otherwise `{}` | `nvl-csv::responsecode.file_not_found` |
| `invalid_csv_input` | 422 | Declared safe scalar/array map; otherwise `{}` | `nvl-csv::responsecode.invalid_csv_input` |
| `csv_parse_failed` | 422 | Declared safe scalar/array map; otherwise `{}` | `nvl-csv::responsecode.csv_parse_failed` |

### Operational logging

`nvl-core.logging` defaults to channel `nvl`, normal verbosity and a CSV quiet override. Configure package `channel`/`verbosity` overrides under `packages`; verbosity is `quiet`, `normal` or `verbose`. Warnings/errors survive every setting. The absent `nvl` channel becomes a stack of the host default; a configured host channel wins. Do not configure a self-referential stack. Doctor diagnoses missing/cyclic channels without logging to them. Stable `nvl.<package>.<operation>.<result>` keys carry bounded diagnostics, never retained tenant/job context. CSV logs one failed-row warning summary per chunk; row details require verbose mode and contain no raw row values.


## License

`nvl/csv` is open-source software licensed under the [MIT license](LICENSE).
