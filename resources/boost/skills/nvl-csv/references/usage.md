# CSV consumer recipes

Read [installation](installation.md) for actual publication/configuration and [API](api.md) for all option and result contracts.

## Analyze and import synchronously

The host supplies a readable local path `$path` and authorizes it before calling CSV. A production handler should call an application-owned persistence service on the selected connection. This runnable capture example has no external side effects:

```php
use Nvl\Csv\Contracts\CSVRowHandler;
use Nvl\Csv\Enums\CSVTypeEnum;
use Nvl\Csv\Services\CsvAnalyzer;
use Nvl\Csv\Services\CsvImporter;

$analysis = app(CsvAnalyzer::class)->analyze($path);
$handler = new class implements CSVRowHandler {
    public array $rows = [];

    public function process(array $row, int $rowNumber): void
    {
        $this->rows[$rowNumber] = $row;
    }
};
$result = app(CsvImporter::class)->import($path, [
    'has_headers' => true,
    'strict_mode' => true,
    'column_mapping' => ['id' => 'id', 'name' => 'name'],
    'column_types' => ['id' => CSVTypeEnum::INTEGER],
    'unique_fields' => ['id'],
    'duplicate_strategy' => 'error',
    'transaction' => true,
], $handler);
```

The source must have `id,name` headers. A mapping emits only mapped fields; adding a type alone also creates a mapping. Configure every retained column. `column_types` keys refer to source fields; `unique_fields` track the mapped value of their source fields. Duplicate comparisons preserve strict scalar types and compound identity/order. A rejected duplicate does not reserve the other unique values from that row.

`analyze` currently accepts only an empty options array. `analyzeDisk($disk, $path)` uses Laravel storage; `quickAnalyze($path)` is a small preview. Analysis retains at most 100 sample rows and can estimate totals for large sources; inspect its `sample_size`, `max_analysis_rows` and `analyzed_rows` metadata rather than treating every count as a complete exact scan.

Important import options are `delimiter`, `enclosure`, `escape`, `encoding`, `has_headers`, `skip_rows`, `limit_rows`, `skip_empty_rows`, `trim_values`, `strict_mode`, `validate_data`, `column_mapping`, `column_types`, `field_mappings`, `unique_fields`, `duplicate_strategy`, `transaction`, `connection`, `chunk_size`, `processing_mode`, `memory_limit` and `metadata`. Supply exact integers/booleans and native arrays, not JSON strings or comma-delimited shorthand. Defaults come from `CSVConfiguration` plus the fluent builder; prefer a fresh importer for each independent operation.

`CSVFieldMapping::typed`, `simple` and `withTransformer` support richer local transformations and validation. A callback mapping is synchronous only. Null/default behavior belongs to each mapping's `required`, `nullable` and `defaultValue` contract; CSV does not infer domain defaults from a database schema.

A successful `import` returns a native array including `total_rows`, `processed_rows`, `successful_rows`, `failed_rows`, `skipped_rows`, `failed_rows_data`, `errors`, `warnings` and metadata. Check failure counts even when no exception was thrown. With transactions enabled, failed rows cause selected-connection rollback. Counts describe attempted work, not committed domain rows. The first 1,000 failed row details are retained.

## Pull processing or bounded commits

For reusable synchronous field logic, choose `StringTransformer`, `NumericTransformer` or `DateTransformer`; combine them with `CSVTransformer::chain` or `when`. They expose `transform($value, $context = [])`; reverse conversion is meaningful only when `supportsReverseTransform()` says so. Numeric CSV transformation is not an exact-money domain substitute.

```php
use Nvl\Csv\Enums\CSVTypeEnum;
use Nvl\Csv\Transformers\CSVTransformer;
use Nvl\Csv\Transformers\StringTransformer;
use Nvl\Csv\Validators\CSVFieldValidator;
use Nvl\Csv\Validators\CSVRowValidator;
use Nvl\Csv\ValueObjects\CSVFieldMapping;

$transformer = CSVTransformer::chain([
    StringTransformer::sanitize(),
    StringTransformer::uppercase(),
]);
$mapping = CSVFieldMapping::withTransformer('name', 'name',
    static fn (mixed $value): mixed => $transformer->transform($value));
$validator = (new CSVRowValidator)->addFieldValidator('email',
    (new CSVFieldValidator)->type(CSVTypeEnum::EMAIL)->required());
$valid = $validator->validate(['email' => 'reader@example.com']);
$messages = $validator->getErrors();
```

Pass `$mapping` through `field_mappings`/`mapField` for a synchronous import. Standalone row validators are explicit host composition: call them from the host's local row workflow and decide how rejection becomes a row failure. Their `getErrors`/`getWarnings` describe the latest validation call; do not assume merely constructing a validator activates it in the importer. These callback-backed mappings cannot enter queued manifests.

```php
use Nvl\Csv\Services\CsvImporter;

$rows = CsvImporter::make()->fromFile($path)->stream();
foreach ($rows as $rowNumber => $row) {
    // Invoke the host's authorized row operation.
}
```

`stream` yields numbered processed rows and closes its handle when iteration finishes. It does not wrap host effects in one transaction. `batch($batchSize, $callback)` requires a positive batch size and commits each batch on `onConnection($name)` independently when transactions are enabled. The host chooses whether partial batch commits meet the domain requirement. `stopOnError()` and `withErrorThreshold()` control synchronous error handling; they are fluent methods, not import option keys. Non-database effects and other database connections remain outside these transactions.

Streaming avoids retaining all output rows. Unique-field tracking still retains distinct keys; compound comparisons remain linear within their fallback bucket. Do not claim constant-memory imports when uniqueness is enabled.

## Export stable, spreadsheet-safe columns

```php
use Nvl\Csv\Services\CsvExporter;

$bytes = app(CsvExporter::class)->export([
    ['id' => 1, 'name' => '=SUM(A1:A2)'],
    ['name' => 'Ada', 'id' => 2],
], [
    'fields' => ['id', 'name'],
    'headings' => ['ID', 'Name'],
    'formula_policy' => 'escape',
]);
```

The result is encoded CSV bytes, not a path or download response. Values and headings are escaped after conversion, including backed enums and Stringable values. `reject` throws for formula-like text; explicit `allow` delegates that trust decision to the host. Fields can be local closures, but provide headings when they cannot be inferred. The first row establishes inferred columns; later extra keys do not expand them.

`export` and the exporter's `withOptions` accept only these keys. Enum names below are in `Nvl\Csv\Enums`; export's `format` and `processing_mode` require enum instances, even though importer processing modes also accept exact backed strings.

| Options | Native values |
| --- | --- |
| `disk`, `path`, `filename` | Strings; disk and destination apply to file-producing operations |
| `format` | `CSVExportFormatEnum` or null; a preset supplies base dialect/BOM/line-ending settings before overrides |
| `processing_mode` | `CSVProcessingModeEnum` |
| `delimiter` | One-byte string or `CSVDelimiterEnum` |
| `enclosure`, `escape` | One-byte enclosure string; escape is empty or one byte |
| `encoding` | Encoding-name string, `CSVEncodingEnum` or null |
| `include_bom`, `include_headers`, `include_index` | Booleans |
| `chunk_size` | Nonnegative integer or null |
| `memory_limit` | Positive integer bytes, `-1` or null |
| `fields`, `headings` | `list<string\|Closure>` fields and `list<string>` headings |
| `metadata` | `array<string,mixed>` |
| `formula_policy` | Exact string `escape`, `reject` or `allow`; default `escape` |

Omitted/null nullable options use their current base/default; they do not clear it. For example, a UTF-8 tab-separated export with a BOM uses a native preset rather than the string `'tsv'`:

```php
use Nvl\Csv\Enums\CSVExportFormatEnum;
use Nvl\Csv\Services\CsvExporter;

$bytes = app(CsvExporter::class)->export($rows, [
    'format' => CSVExportFormatEnum::TSV,
    'encoding' => 'UTF-8',
    'include_bom' => true,
    'fields' => ['id', 'name'],
    'headings' => ['ID', 'Name'],
]);
```

For a large artifact use `CsvExporter::make()->withOptions(['filename' => 'people.csv', 'disk' => 'local', 'path' => 'exports'])->fromArray($rows)` or the native query/collection/stream variants. These return file metadata including `storage_path`, `path`, `disk`, `file_size`, `row_count` and `column_count`. The byte-returning path checks memory before materializing its final string. Keep destination disk/path selection in trusted host configuration.

## Register and enqueue durable handlers

Define the class in the application's normal autoloaded code, then register it during host provider boot. This example records no external effect; replace its body with an idempotent host operation:

```php
namespace App\Csv;

use Nvl\Csv\Contracts\CSVRowHandler;

final class ContactRowHandler implements CSVRowHandler
{
    public function process(array $row, int $rowNumber): void
    {
        // Call the host's idempotent contact importer.
    }
}
```

```php
use App\Csv\ContactRowHandler;
use Nvl\Csv\Services\CSVAsyncProcessor;
use Nvl\Csv\Services\CSVHandlerRegistry;

app(CSVHandlerRegistry::class)->register('contacts', ContactRowHandler::class, 1);
$batch = app(CSVAsyncProcessor::class)
    ->fromFile($path)
    ->withOptions(['strict_mode' => true, 'duplicate_strategy' => 'error'])
    ->withChunkSize(500)
    ->usingHandler('contacts', 1)
    ->processAsync();
```

Alias/version registration is immutable; change version when the handler contract changes. Chunk size accepts 1–100,000. Queued `encoding`, `processing_mode` and `duplicate_strategy` accept enum instances or exact backed strings; producer normalization makes manifests JSON safe. Delimiter and column-type enums normalize too. Arbitrary option objects, closures and callback-bearing mappings are refused.

The package has no configuration file to publish. For queued work, the host may create `config/nvl-csv.php` with its actual shared private disk and queue settings. This example assumes the host has provisioned `filesystems.disks.csv-work`:

```php
return [
    'work' => ['disk' => 'csv-work'],
    'queue' => ['connection' => null, 'name' => null],
    'locks' => ['store' => null],
];
```

`work.disk` defaults to `local`; configure shared private storage accessible to the producer and every worker when they run on different machines. Set an explicit `queue.connection`/`queue.name` to override routing. Null/omitted queue settings inherit Core's matching settings, then Laravel's `queue.default` and that connection's queue name (or `default`). Provision Laravel batch storage separately; CSV supplies no batch migration. `locks.store` inherits Core then `cache.default`; select an atomic cache shared by every worker, such as the same Redis or database lock store. A process-local array cache does not coordinate separate workers.

Production must begin outside transactions on both the selected import connection and the actual database batch repository connection. Jobs use after-commit delivery. The job timeout is 300 seconds and chunk lock lease is 360 seconds; set the connection retry window above 360 seconds. All workers must use the same lock store and code; stop/restart old workers during rollout. Failed work remains available for recovery. The lock covers fresh owner/manifest admission, completion checks, handler calls, sealing and source deletion. Completion seals prevent replay of fully completed chunks, but a partial failure or crash can repeat earlier effects; the host must choose its durable idempotency key. Mapping validation failures are `CSVValidationException` exceptions carrying the source row and field errors; identity/checksum failures remain ownership violations.

Native Ownership envelopes are captured and re-admitted in workers when that optional runtime is used. Null context supplies no owner authority. No actor is inferred from Auth and no package mutation policy authorizes host handler effects.

Staged rows and manifests may contain PII. Define a host retention period for failed or cancelled imports. Retire only the reviewed canonical work directory on the configured private disk after cancelling/reconciling the import and draining active/retry deliveries, under the batch’s matching admitted owner/unowned boundary when Ownership is enabled. Successful batch cleanup is automatic; cancellation alone does not delete staging. There is no public CSV failed-work cleanup method or prune command: implement scoped storage retirement in the host, and preserve recoverable work until its retry/reconciliation window closes.

`CSVAsyncProcessor::getBatchStatus($batchId)` returns a native status/count array or `['status' => 'not_found']`; `cancelBatch($batchId)` returns whether a batch was found and cancelled. The host authorizes monitoring/cancellation and uses the appropriate admitted Ownership context. Cancellation is not compensation for effects from already processed rows.

## Failure and verification decisions

Handle `CSVConfigurationException` for bad options/dialects, file-not-found exceptions for missing sources, `CSVParseException` for malformed headers/encoding/width, `CSVValidationException` for field or duplicate failure, and `CSVMemoryException` for insufficient budgets. A result can also contain row failures without throwing.

Test a real small file through the public service, exact field projection, explicit encoding, malformed rows, independent unique fields, transaction rollback, output ordering/escaping and registered-handler replay. Run only affected application tests during iteration. Do not infer real worker or distributed retry guarantees from a bus fake alone.
