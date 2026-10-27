# NVL CSV

`nvl/csv` provides CSV analysis, synchronous import, export, and staged Laravel queue batches. It requires PHP 8.4, Laravel 13, and `nvl/core:^6.0`. Laravel discovers `Nvl\Csv\Providers\CsvServiceProvider`. The package has no model, table, route, or Data/TypeScript runtime registration.

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

`CsvImporter` also supports `fromFile()`, `fromDisk()`, `mapField()`, `stream()`, `batch()`, and `run()` for synchronous workflows. `CSVFieldMapping` retains validated field names, types, and mapping rules. Local closures in `processRow()`, `onProgress()`, and `batch()` execute in the current process; they cannot be queued. Imports use the selected Laravel database connection (`onConnection()` or the `connection` option). A result with failed rows rolls back the enclosing import transaction. Host writes on another connection or external systems are outside that transaction. Streaming has no enclosing transaction; each configured batch has its own transaction. The first 1,000 failed row details are retained while counts stay complete.

`CsvExporter` also supports `fromArray()`, `fromQuery()`, `fromCollection()`, and `stream()` for disk-backed exports. Set explicit `fields` and `headings` when order matters. The first row determines inferred columns; later extra keys do not add columns. The `formula_policy` option defaults to `escape`, can be `reject`, or can be explicitly set to `allow` for trusted data. It applies after each value is converted to text, including `Stringable` and backed-enum cells, and to explicit or inferred headings. The default CSV escape character is empty so quotes use standard doubled enclosures. `CSVConfiguration` enforces dialect and processing limits for import and export.

The native `export()` entry point returns a PHP string, so its final encoded bytes must fit in memory. Before materializing that string, it checks the output size against the configured memory budget with room for a second copy and throws `CSVMemoryException` when the budget is insufficient. Use the disk-backed `fromArray()`, `fromQuery()`, `fromCollection()`, or `stream()` paths for large artifacts; those copy the encoded stream to Laravel storage without returning its full bytes.

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

Create Laravel's queue batch repository table and configure workers for the selected connection/queue. The worker timeout is 300 seconds, so set the queue connection's `retry_after` above it. Queue settings use `nvl-csv.queue.connection` and `nvl-csv.queue.name`, then Core's queue defaults. Configure `nvl-csv.work.disk` to a private writable disk shared by producer and worker; it defaults to `local`.

`processAsync()` returns a real batch synchronously and must start outside transactions on both the selected import connection (`connection`, or the default) and the actual database batch repository connection. An ambient transaction is rejected before staging private work. Chunk jobs force after-commit delivery, including on the sync queue with `after_commit=false`, so handlers run after the batch metadata transaction commits. Non-database repositories retain their supported dispatch behavior; an unrelated database transaction does not replace these source boundaries.

The producer captures the native Ownership envelope and stages bounded JSON chunks and a sealed manifest under an owner-partitioned work path. Workers re-admit the envelope, validate the work identity and checksums, resolve the registered handler class, and reject serialized consumer callbacks and callback-bearing mappings. Failed work remains available for retries; completed work is cleaned after a successful batch. A completion seal prevents replay after a fully completed chunk. If a handler fails partway through a chunk, its earlier external side effects may repeat. Handlers must implement application-level idempotency for those effects. Queue batching is not a durable exactly-once transaction or outbox.

Queued `withOptions()` stores JSON-safe snake_case values. `encoding`, `processing_mode`, and `duplicate_strategy` accept their enum instances or exact backed strings (for example, `'Windows-1252'`, `'stream'`, and `'error'`); enum instances become those strings in the staged manifest. A `CSVDelimiterEnum` becomes its one-character delimiter and `column_types` enum values become their backed strings. Unknown enum strings and arbitrary objects or closures fail at configuration. Queued parsing stops and retains no work when the `error` duplicate policy finds a repeated `unique_fields` value. The worker writes a completion seal successfully before removing the source chunk; a failed seal write fails the job and retains the chunk for recovery.

`nvl/ownership` is optional. When installed and enabled, use native Ownership runner admission and Core's queue envelopes; null context grants no owner authority. CSV has no relational resources to adopt. Unowned hosts remain supported without the Ownership package.

## Testing a host

Test the service with a real small CSV fixture and a host handler. Cover delimiter and encoding, malformed rows, duplicate strategy, import rollback on the selected connection, export field order and formula policy, and queued handler registration, tamper rejection, cancellation, retry, and replay. Use Laravel storage and bus fakes where they represent the effect being asserted. Read [UPGRADING.md](UPGRADING.md) before migrating from the former DTO or Tenancy APIs. CSV declares [no domain events](docs/events.md).
