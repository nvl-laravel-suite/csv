# Upgrading NVL CSV to Light

Upgrade CSV and its selected NVL dependency closure together to the transitional `^6.0` line. The Laravel floor is 13 and PHP floor is 8.4. Install Laravel Testbench `^11.0` for host package tests.

## Replace the runtime API

- Replace `CSVAnalyzerService` with `CsvAnalyzer`; use `analyze($path)`, `analyzeDisk($disk, $path)`, or `quickAnalyze($path)` and read snake_case result arrays.
- Replace `CSVImport` with `CsvImporter` and `CSVExport` with `CsvExporter`. The concrete services own parsing, validation, transaction, and export guarantees. Their native `import($path, $options, $handler)` and `export($rows, $options)` operations use arrays. Existing fluent behavior is available through the native classes where documented in the README.
- Replace `CSVAnalysisResultData`, import/export options Data, progress Data, and import/export result objects with documented array shapes. Move HTTP DTOs, Actions, and generated TypeScript types into the consuming application. There is no package Data or TypeScript runtime provider.
- Replace former service contract injection with native services and the `CSVRowHandler` boundary. `CSVFieldMapping`, `CSVWorkReference`, and `CSVConfiguration` remain because they enforce mapping, work identity, and CSV dialect invariants.
- Use snake_case option keys. Unknown keys and invalid types fail rather than being silently accepted. Export values that could be spreadsheet formulas are escaped by default; select `reject` or explicitly `allow` through `formula_policy`.

## Queue migration

Drain or cancel old CSV jobs and staged manifests before deploying the native worker. They contain retired callback or Tenancy payloads and cannot be restored through the new work reference. Register versioned `CSVRowHandler` classes through `CSVHandlerRegistry` at application boot and enqueue by alias with `CSVAsyncProcessor::usingHandler()`. Queued consumer closures, callback-bearing field mappings, and arbitrary object options are refused.

For queued options, pass enum instances or their exact backed strings for `encoding`, `processing_mode`, and `duplicate_strategy`. The manifest stores the backed strings. `CSVDelimiterEnum` normalizes to its delimiter character; `column_types` enum values normalize to backed strings. Other objects remain unsupported. Invalid enum strings fail before staging.

Configure a shared private work disk, Laravel's queue batch repository, and workers with `retry_after` above the 300 second job timeout. Native Ownership captures and re-admits worker context; the retired `TenantContext`, `TenantRunner`, and legacy work-store fallback are removed. A null owner context is never an authority grant. The optional `nvl/ownership` integration must be configured explicitly. `CSVWorkReference` now carries a native Ownership envelope.

CSV chunk checksums and completion seals protect staged work and suppress replay after a complete chunk. A retry after a partial handler failure can repeat prior row side effects. Use application idempotency keys or transactions appropriate to the handler's target system. Do not infer exactly-once external effects from the queue batch status.

## Operational checks

Port host tests to native arrays, registered handlers, and the selected database connection. Verify quoted fields, encodings, malformed rows, duplicate policy, rollback, formula handling, ownership admission, manifest tampering, cancellation, and retries. Restart queue workers after the new handler registry is deployed. See the [README](README.md) for current examples.
