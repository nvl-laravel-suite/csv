# Upgrading NVL CSV to Light

Upgrade CSV and its selected NVL dependency closure together to the native `^7.0` line. The Laravel floor is 13 and PHP floor is 8.4. Install Laravel Testbench `^11.0` for host package tests.

## Replace the runtime API

- Replace `CSVAnalyzerService` with `CsvAnalyzer`; use `analyze($path)`, `analyzeDisk($disk, $path)`, or `quickAnalyze($path)` and read snake_case result arrays.
- Replace `CSVImport` with `CsvImporter` and `CSVExport` with `CsvExporter`. The concrete services own parsing, validation, transaction, and export guarantees. Their native `import($path, $options, $handler)` and `export($rows, $options)` operations use arrays. Existing fluent behavior is available through the native classes where documented in the README.
- Replace `CSVAnalysisResultData`, import/export options Data, progress Data, and import/export result objects with documented array shapes. Move HTTP DTOs, Actions, and generated TypeScript types into the consuming application. There is no package Data or TypeScript runtime provider.
- Replace former service contract injection with native services and the `CSVRowHandler` boundary. `CSVFieldMapping`, `CSVWorkReference`, and `CSVConfiguration` remain because they enforce mapping, work identity, and CSV dialect invariants.
- Use snake_case option keys. Unknown keys and invalid types fail rather than being silently accepted. Export values that could be spreadsheet formulas are escaped by default; select `reject` or explicitly `allow` through `formula_policy`.

## Queue migration

Drain or cancel old CSV jobs and staged manifests before deploying the native worker. They contain retired callback or Tenancy payloads and cannot be restored through the new work reference. Register versioned `CSVRowHandler` classes through `CSVHandlerRegistry` at application boot and enqueue by alias with `CSVAsyncProcessor::usingHandler()`. Queued consumer closures, callback-bearing field mappings, and arbitrary object options are refused.

Move the body of a former `processRow()` closure into the registered handler's `process(array $row, int $rowNumber)` method. Use the batch ID from `processAsync()` with `getBatchStatus()` for progress and correlate Laravel's `BatchFinished` event for completion. Keep local callback workflows on `CsvImporter`.

For queued options, pass enum instances or their exact backed strings for `encoding`, `processing_mode`, and `duplicate_strategy`. The manifest stores the backed strings. `CSVDelimiterEnum` normalizes to its delimiter character; `column_types` enum values normalize to backed strings. Other objects remain unsupported. Invalid enum strings fail before staging.

Configure a shared private work disk, Laravel's queue batch repository, and workers with `retry_after` above the 360 second chunk lock lease (the job timeout is 300 seconds). Native Ownership captures and re-admits worker context; the retired `TenantContext`, `TenantRunner`, and legacy work-store fallback are removed. A null owner context is never an authority grant. The optional `nvl/ownership` integration must be configured explicitly. `CSVWorkReference` now carries a native Ownership envelope.

Configure `nvl-csv.locks.store` to an atomic cache shared by every worker; omission inherits Core then Laravel's cache store. Chunk jobs recheck current admission inside that lock. Its 360-second lease exceeds the 300-second job timeout, so set `retry_after` above 360 seconds and restart old workers before accepting new deliveries. Mapping validation now raises `CSVValidationException` with row/field diagnostics instead of an ownership violation.

Failed/cancelled private staging can contain PII and remains available for recovery. Define host retention and scoped storage retirement after reconciliation and after all active/retry deliveries have drained, under the batch’s matching admitted owner/unowned boundary. Cancelling a batch does not itself delete staging; no public failed-work cleanup method or prune command is provided.

CSV chunk checksums and completion seals protect staged work and suppress replay after a complete chunk. A retry after a partial handler failure can repeat prior row side effects. Use application idempotency keys or transactions appropriate to the handler's target system. Do not infer exactly-once external effects from the queue batch status.

## Upgrading to the next release

- Drain or finish queued CSV batches before deploying. Staged work is now format version 3 with canonical mapped rows; workers refuse version 2 manifests, and older workers refuse version 3.
- `CSVAsyncProcessor::processAsync()` throws `CSVDispatchException` when dispatch fails after delivery began, for example when a sync-queue handler fails. The exception's `work` property and previous exception identify the retained staged work; reconcile handler effects before retrying or retiring it. Failures before dispatch still delete the staged work and rethrow the original exception.
- Queued `mapField()` mappings now run on the producer: duplicate, type and required-header failures are raised by `processAsync()` before anything is staged. A mapping rejection is a `CSVValidationException` with the source row and field errors (`getAffectedRows()`, `getRowErrors()`); it no longer fails a worker job, and no staged work is retained for it.
- The importer's input encoding has one source: `CSVConfiguration::$encoding`. The constructor, `configure()`, `withOptions(['encoding' => ...])` and `withEncoding()` all set it, and the last call wins; a byte order mark in the file still takes precedence. An encoding name that is not a `CSVEncodingEnum` value (case-insensitive) now throws `CSVConfigurationException` when the importer receives the configuration.
- `CSVFieldMapping` throws `InvalidArgumentException` for a `format` on a type other than `DATE`, `DATETIME` or `INSTANT`. With a supported type, the format is applied strictly, so cells in another layout fail validation.
- Empty cells of optional nullable typed mappings become `null` (previously the cast threw).
- Use `CSVTypeEnum::INSTANT` for offset-qualified timestamps such as PostgreSQL `timestamptz`. `DATETIME` keeps returning wall time (`Y-m-d H:i:s`) and does not apply the offset.
- `CSVFieldValidator::range()` accepts `int|float` bounds; pass integers to compare integer cells exactly.
- Logical records over 8 MiB fail with `CSVParseException` in the importer and analyzer.
- A source that ends inside an unclosed quoted field now fails with `CSVParseException` in the importer, analyzer and `processAsync()` (nothing is staged). Previously the remaining lines were silently folded into one record; repair such files before importing.
- Formatted dates whose weekday name contradicts the written date (for example `Mon, 17 Nov 2026 12:00:00 +0200` with `r`, a Tuesday) now fail validation instead of being moved to the next matching weekday.

## Behaviour corrections after 7.0.1

- `trim_values` (default `true`) now trims data cells as well as headers. Pass `'trim_values' => false` where surrounding whitespace is significant.
- `CSVTypeEnum::DATE`/`DATETIME` and `DateTransformer` without an input format no longer accept relative, year-less or time-only text (`tomorrow`, `sat`, `2024`, `12:30`, `1.5`); such cells fail validation or return the transformer default. `CSVTypeEnum::FLOAT` rejects values that overflow to infinity.
- `NumericTransformer` reads `1.234,56` and `1,5` as decimal-comma numbers; `1,234` and `1,234.56` keep their grouping meaning.
- `CSVEncodingEnum::UTF16->getPhpEncoding()` returns `UTF-16LE` and `UTF32` returns `UTF-32BE`, matching their byte order marks on every platform.
- `CSVAsyncProcessor::processAsync()` refuses a source without data rows and aborts staging on any failed source row; cancelled batches keep their staged work.
- `CsvExporter::withOptions()` keeps a destination already selected with `disk()`, `path()` or `filename()` when it omits those keys; `CsvImporter::withOptions()` likewise keeps a selected source.
- `CSVFilter::field()->isEmpty()`/`isNotEmpty()` treat `0` and `"0"` as values. `CSVFieldValidator::email()` and `phone()` reject line breaks.

## Operational checks

Port host tests to native arrays, registered handlers, and the selected database connection. Verify quoted fields, encodings, malformed rows, duplicate policy, rollback, formula handling, ownership admission, manifest tampering, cancellation, and retries. Restart queue workers after the new handler registry is deployed. See the [README](README.md) for current examples.
