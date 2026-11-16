# NVL CSV

`nvl/csv` provides CSV analysis, synchronous import, export, and staged Laravel queue batches. It requires PHP 8.4, Laravel 13, and `nvl/core:^7.0`. Laravel discovers `Nvl\Csv\Providers\CsvServiceProvider`. The package has no model, table, route, or Data/TypeScript runtime registration.

## Native operations

```php
use Nvl\Csv\Services\CsvAnalyzer;
use Nvl\Csv\Services\CsvImporter;
use Nvl\Csv\Services\CsvExporter;
use Nvl\Csv\Contracts\CSVRowHandler;

$analysis = app(CsvAnalyzer::class)->analyze($absolutePath);
// $analysis['headers'], $analysis['row_count'], $analysis['issues']

$handler = new class implements CSVRowHandler {
    public function process(array $row, int $rowNumber): void
    {
        // Call a host Action on the selected database connection.
    }
};
$result = app(CsvImporter::class)->import($absolutePath, [
    'strict_mode' => true,
    'transaction' => true,
], $handler);
// $result['successful_rows'], $result['failed_rows'], $result['errors']

$csv = app(CsvExporter::class)->export(
    [['id' => 1, 'name' => 'Ada']],
    ['headings' => ['id', 'name'], 'formula_policy' => 'escape'],
);
```

Options and results are documented arrays with snake_case keys. Unknown options and invalid types fail explicitly. `CsvAnalyzer::analyzeDisk($disk, $path)` reads from a Laravel disk; `quickAnalyze($path)` gives a small structural preview. Encoding and dialect detection are recommendations that the host should confirm before a consequential import.

Local analysis accepts readable regular files and local `file://` URIs. Missing sources, directories and unreadable local files fail before parsing with a path-free `CSVFileNotFoundException`; disk analysis also rejects directories before requesting file metadata.

Duplicate checks preserve strict value types across all configured unique fields. A row skipped for one duplicate field does not reserve values from its other fields. Scalar membership uses keyed lookup; compound mapped values retain strict comparisons.

`CsvImporter` also supports `fromFile()`, `fromDisk()`, `mapField()`, `stream()`, `batch()`, and `run()` for synchronous workflows. `withOptions()` keeps a source selected earlier with `fromFile()` or `fromDisk()`; a `file_path` option is validated like `fromFile()`. `trim_values` (default `true`) trims headers and every data cell before validation, duplicate checks and empty-row skipping; set it to `false` to preserve surrounding whitespace. `CSVTypeEnum::DATE`/`DATETIME` accept complete calendar dates only: relative words, year-less or time-only values (`tomorrow`, `2024`, `12:30`) are rejected, and `FLOAT` rejects values that overflow to infinity. Rollover dates such as `2026-02-31` are rejected. `DATETIME` is wall time (`Y-m-d H:i:s`) and drops any offset; use `INSTANT` for offset-qualified values, which it returns in UTC as RFC 3339 with microseconds. A mapping `format` (date types only) is parsed strictly. Empty cells of optional nullable typed mappings become `null`. The input encoding comes from `CSVConfiguration::$encoding`, set by the constructor, `configure()`, the `encoding` option or `withEncoding()` (last call wins; a byte order mark takes precedence). Records may end in CR, LF or CRLF, and one logical record is limited to 8 MiB. `CSVEncodingEnum::UTF16` and `UTF32` without a byte order mark are read and written as UTF-16LE and UTF-32BE. `CSVFieldMapping` retains validated field names, types, and mapping rules. Local closures in `processRow()`, `onProgress()`, and `batch()` execute in the current process; they cannot be queued. Imports use the selected Laravel database connection (`onConnection()` or the `connection` option). A result with failed rows rolls back the enclosing import transaction. Host writes on another connection or external systems are outside that transaction. Streaming has no enclosing transaction; each configured batch has its own transaction. The first 1,000 failed row details are retained while counts stay complete.

`CsvExporter` also supports `fromArray()`, `fromQuery()`, `fromCollection()`, and `stream()` for disk-backed exports. A destination chosen with `disk()`, `path()` or `filename()` survives a later `withOptions()` call that omits those keys; all other options apply per call. Set explicit `fields` and `headings` when order matters. The first row determines inferred columns; later extra keys do not add columns. The `formula_policy` option defaults to `escape`, can be `reject`, or can be explicitly set to `allow` for trusted data. It guards text values after conversion, including `Stringable` and backed-enum cells, and explicit or inferred headings. Native integer and float values retain their numeric representation, including negative numbers. Numeric-looking strings remain untrusted text. The default CSV escape character is empty so quotes use standard doubled enclosures. `CSVConfiguration` enforces dialect and processing limits for import and export.

The native `export()` entry point returns a PHP string, so its final encoded bytes must fit in memory. Before materializing that string, it checks the output size against the configured memory budget with room for a second copy and throws `CSVMemoryException` when the budget is insufficient. Use the disk-backed `fromArray()`, `fromQuery()`, `fromCollection()`, or `stream()` paths for large artifacts; those copy the encoded stream to Laravel storage without returning its full bytes.

Use `writeToResource($destination, $rows, $options)` to write a lazy iterable to an open writable stream, such as `php://output` in a Laravel `streamDownload` callback. It applies the same dialect, encoding, BOM, headings, field order and formula policy as `export()`. The caller owns the stream and its position. If a later row or write fails, bytes already written remain in the stream.

### Result arrays

| Operation | Stable result keys |
| --- | --- |
| `CsvImporter::import()`, `run()`, `batch()` | `total_rows`, `processed_rows`, `successful_rows`, `failed_rows`, `skipped_rows`, `failed_rows_data`, `processing_time`, `started_at`, `completed_at`, `errors`, `warnings`, `metadata` |
| `CsvExporter::writeToResource()` | `row_count`, `column_count`, `processing_time`, `errors`, `warnings`, `metadata` |
| `CsvExporter` disk-backed operations | The resource export keys plus `disk`, `path`, `storage_path`, `url`, `file_size` |
| `CSVAsyncProcessor::getBatchStatus()` | `status` alone when `not_found`, otherwise `id`, `status`, `processed_jobs`, `pending_jobs`, `failed_jobs`, `total_jobs` |

Counts and file size are integers, file size is in bytes, and `processing_time` is seconds as a float. `errors` and `warnings` are lists of strings. Each retained import failure contains `row_number`, `data`, and `error`. Import timestamps are Carbon instances. Disk export `storage_path` is relative to its Laravel disk. `path` is the adapter path where available and `url` can be null. `metadata` carries host-supplied metadata and operation-specific keys. `processed_rows` counts successful and failed attempts. A transactional import can roll back recorded attempts after a failed row.

## Queued rows

Register a stable host `CSVRowHandler` class with an alias and version at boot, then enqueue a readable local file:

```php
use Nvl\Csv\Services\CSVAsyncProcessor;
use Nvl\Csv\Services\CSVHandlerRegistry;

app(CSVHandlerRegistry::class)->register('contacts', ImportContactRow::class, 1);
$batch = app(CSVAsyncProcessor::class)
    ->fromFile($absolutePath)
    ->withChunkSize(500)
    ->usingHandler('contacts', 1)
    ->processAsync();
```

Create Laravel's queue batch repository table and configure workers for the selected connection/queue. The worker timeout is 300 seconds and chunk lock lease is 360 seconds, so set the queue connection's `retry_after` above 360 seconds. The package ships no configuration file and no configuration publish tag (its only publish tags are `nvl-csv-translations` and `nvl-csv-skills`); define the following `nvl-csv` keys in a host-owned configuration file. Every key is optional:

| Key | Default when omitted or `null` |
| --- | --- |
| `nvl-csv.queue.connection` | `nvl-core.queue.connection`, then Laravel's `queue.default` |
| `nvl-csv.queue.name` | `nvl-core.queue.name`, then the selected connection's `queue`, then `default` |
| `nvl-csv.locks.store` | `nvl-core.locks.store`, then Laravel's `cache.default` |
| `nvl-csv.work.disk` | `local` when the key is omitted; an explicit `null`, empty or non-string value is refused when work is enqueued or read |

A configured queue or lock value that is empty or not a string throws `InvalidArgumentException` instead of falling back. Configure `nvl-csv.work.disk` to a private writable disk shared by producer and worker; a worker refuses work staged on a different disk than its own configuration names. Select a shared atomic cache for `nvl-csv.locks.store`; every worker must use the same durable lock store. Failure logging follows Core's `nvl-core.logging` settings. A process-local array cache cannot coordinate separate workers. Restart old workers when deploying this change.

Register Core's `DoctorServiceProvider` and run `php artisan nvl:doctor`. The `nvl/csv` checks report the effective work disk, atomic lock store and retry timing without writing staging files. Verify separately that each producer and worker mounts the same private disk and uses the same cache backend. For SQS, confirm the broker visibility timeout exceeds the 360-second lock lease.

`processAsync()` returns a real batch synchronously and must start outside transactions on both the selected import connection (`connection`, or the default) and the actual database batch repository connection. An ambient transaction is rejected before staging private work. Chunk jobs force after-commit delivery, including on the sync queue with `after_commit=false`, so handlers run after the batch metadata transaction commits. Non-database repositories retain their supported dispatch behavior; an unrelated database transaction does not replace these source boundaries.

The producer captures the native Ownership envelope and stages bounded JSON chunks and a sealed manifest under an owner-partitioned work path. Workers re-admit the envelope, validate the work identity and checksums, resolve the registered handler class, and reject serialized consumer callbacks and callback-bearing mappings. Failed work remains available for retries; a mapping rejection is raised by `processAsync()` before staging (as `CSVValidationException` with the source row and field errors), so no work is retained for it; completed work is cleaned after a successful batch. A cancelled batch keeps its unprocessed chunks and manifest even after its remaining deliveries drain. A chunk lock covers fresh admission, replay checks, handler effects, completion sealing and source deletion. A completion seal prevents replay after a fully completed chunk. If a handler fails partway through a chunk, its earlier external side effects may repeat. Handlers must implement application-level idempotency for those effects. Queue batching is not a durable exactly-once transaction or outbox.

Staging can contain PII. Set a host retention policy for failed/cancelled imports and retire only reviewed canonical work directories on the configured private disk after retries and reconciliation are finished and active deliveries are drained. Authorize retirement under the batch’s matching owner or unowned boundary when Ownership is enabled; unavailable-owner cleanup needs a separately reviewed host maintenance procedure, never a forged envelope or path-derived authority. Cancellation alone does not delete staged bytes. CSV has no public failed-work cleanup method or prune command; scoped storage retirement belongs to the host. Mapping rejections raise `CSVValidationException` with source row/field errors and retain work for recovery.

Queued `withOptions()` stores JSON-safe snake_case values. `encoding`, `processing_mode`, and `duplicate_strategy` accept their enum instances or exact backed strings (for example, `'Windows-1252'`, `'stream'`, and `'error'`); enum instances become those strings in the staged manifest. A `CSVDelimiterEnum` becomes its one-character delimiter and `column_types` enum values become their backed strings. Unknown enum strings and arbitrary objects or closures fail at configuration. Queued parsing stops and retains no work when any source row fails: a repeated `unique_fields` value under the `error` duplicate policy, a row of the wrong width in `strict_mode`, or a mapped value that fails validation or casting. A source without data rows is refused with `CSVValidationException` rather than creating an empty batch. Fluent `mapField()` mappings run on the producer, so uniqueness, types and required headers apply across all chunks and workers receive canonical rows. Chunks are also split to stay within 8 MiB of encoded JSON. If dispatch fails after delivery began (for example a failing sync-queue handler), the staged work is kept and `CSVDispatchException` exposes its `work` reference. The worker writes a completion seal successfully before removing the source chunk; a failed seal write fails the job and retains the chunk for recovery.

`nvl/ownership` is optional. When installed and enabled, use native Ownership runner admission and Core's queue envelopes; null context grants no owner authority. CSV has no relational resources to adopt. Unowned hosts remain supported without the Ownership package.

## Testing a host

Test the service with a real small CSV fixture and a host handler. Cover delimiter and encoding, malformed rows, duplicate strategy, import rollback on the selected connection, export field order and formula policy, and queued handler registration, tamper rejection, cancellation, retry, and replay. Use Laravel storage and bus fakes where they represent the effect being asserted. Read [UPGRADING.md](UPGRADING.md) before migrating from the former DTO or Tenancy APIs. CSV declares [no domain events](docs/events.md).

Shared [service contracts, model extensions and composition](https://github.com/nvl-laravel-suite/laravel-suite/blob/main/docs/consumer-api.md) apply alongside the package-specific rules above.
