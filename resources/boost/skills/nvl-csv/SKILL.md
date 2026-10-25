---
name: nvl-csv
description: Implement or test native CSV analysis, import, export, and registered queued row handling in Laravel 13.
---

# NVL CSV

Use `Nvl\Csv\Services\CsvAnalyzer`, `CsvImporter`, and `CsvExporter` for package-owned validation and processing. Their native entry points are `analyze($path, $options = [])`, `import($path, $options, CSVRowHandler $handler)`, and `export($rows, $options)`. Options and results are snake_case arrays. Keep host HTTP DTOs, Actions, and TypeScript types in the consuming application. Inspect the package README and service option allowlists before calling them.

Treat source CSV as untrusted. Select dialect, encoding, header policy, duplicate strategy, row limits, selected database connection, and error threshold deliberately. The default escape is empty for doubled quotes. Synchronous import can use local callbacks and rolls back its selected connection when rows fail; side effects on other connections or external systems need host coordination. Stream for pull processing and use bounded batches where needed. Field mapping and CSV configuration objects enforce their own invariants.

Exports should set trusted fields and headings when order matters. Spreadsheet formula cells are escaped by default; choose `reject` for strict output or explicit `allow` only for trusted values. Test reordered keys, quoting, encodings, malformed rows, and formula handling.

For queued imports, register a durable `CSVRowHandler` class and immutable alias/version through `CSVHandlerRegistry`, then use `CSVAsyncProcessor::usingHandler()`. Consumer closures, callback-bearing mappings, and arbitrary serialized option objects are refused. Configure a shared private work disk, a queue batch repository, and a retry window greater than the 300 second job timeout. Staged JSON chunks carry checksums and native Ownership envelopes. Workers re-admit the envelope and resolve the handler from the registry. Do not treat completion seals as exactly-once protection for handler side effects after partial failure; make host handlers idempotent.

Ownership is optional and explicit. Use native Ownership runner admission when installed; null context grants no owner privilege. No Tenancy bridge API or Data/TypeScript runtime belongs in the CSV package. CSV declares no domain events. Use the owning package tests and independent installed consumer to verify package boundaries.
