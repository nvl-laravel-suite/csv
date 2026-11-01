---
name: nvl-csv
description: Use when integrating CSV analysis, synchronous import or export, streaming batches, or registered queued CSV handlers in Laravel.
---

# NVL CSV

Use the native CSV services for parsing, validation and processing.
The application owns uploads, authentication, domain persistence and HTTP responses.
CSV exposes no package model, route, migration or domain event.

## Read the relevant reference

- [Installation](references/installation.md): prerequisites, provider discovery, published resources and configuration.
- [API](references/api.md): exact public signatures, enum values and result shapes.
- [Usage](references/usage.md): option allowlists, synchronous mapping, streaming, exports and durable queued handlers.

Read the usage section for the operation and its options; consult API for native types and factories.

## Choose the operation

| Need | Native capability |
| --- | --- |
| Inspect an untrusted source | `CsvAnalyzer::analyze`, `analyzeDisk`, `quickAnalyze` |
| Persist rows through host logic | `CsvImporter::import` with `CSVRowHandler` |
| Pull rows or commit bounded batches | Importer's `stream` or `batch` |
| Return encoded CSV bytes | `CsvExporter::export` |
| Write a large artifact to a disk | Exporter's `fromQuery`, `fromCollection`, `fromArray`, `stream` |
| Enqueue registered row processing | `CSVHandlerRegistry` and `CSVAsyncProcessor` |
| Compose local field/row rules | `CSVTransformer` family and `CSVFieldValidator`/`CSVRowValidator` |

## Input and output contract

Options and results use snake_case native arrays.
Reject unknown keys rather than forwarding request input wholesale.
Analysis accepts no options currently; its dialect and encoding findings are recommendations.
Select the input encoding, delimiter, headers, mapping, duplicate strategy and row limits deliberately.
Default dialect uses comma, double-quote enclosure and empty escape for doubled quotes.
Headers, empty-row skipping and value trimming default to enabled; strict row width defaults to disabled.
A supplied mapping selects output fields, so declare every field the handler needs.
Use explicit null only where the operation permits it; omission selects the documented default or preserves fluent configuration.
Results report counts and diagnostics; they do not replace host domain models.

## Persistence and effects

Synchronous imports default to a transaction on the selected connection.
A result containing failed rows rolls that import back; writes on another connection and external effects need host coordination.
Streaming opens no encompassing transaction; each configured batch has its own transaction.
Retained failure details are bounded to 1,000 while total counts remain complete.
Duplicate tracking retains seen values and therefore grows with the number of distinct keys.
Ordinary scalar values use keyed buckets; compound values retain strict comparisons.

## Output and workers

Choose trusted field order and headings explicitly for stable exports.
Formula policy defaults to `escape`, including headings and converted Stringable/enum cells.
Use `reject` for strict rejection or `allow` only when the host deliberately trusts the data.
The byte-returning `export` must fit its final string in memory; disk-backed paths stream output.
Queued work accepts an immutable registered handler alias/version, not consumer closures.
Chunks and manifests are private, checksum protected and bound to captured native Ownership context.
A shared atomic cache lock serializes each captured chunk and rechecks admission before effects.
Completion seals prevent completed-chunk replay; partial handler effects can repeat after failure or a crash.
Make host effects idempotent and keep work available through the retry window.

## Host decisions and verification

CSV has no actor policy seam; the host authorizes source access, destinations and handler effects.
Ownership is optional; use admitted native context when enabled, never a legacy Tenancy bridge.
Boot must remain possible with the optional Ownership library absent.
Use a shared private work disk, Laravel batch storage and a shared atomic `nvl-csv.locks.store` (Core/cache default inheritance applies).
The job timeout is 300 seconds and lock lease is 360 seconds; set `retry_after` above the lease and restart old workers before rollout.
Staging can contain PII. The host owns failed-work retention and storage retirement after retries/reconciliation; cancellation alone does not delete it.
CSV exposes no public failed-work cleanup method or prune command.
Test actual parsing, mapped values, selected-connection rollback, formula policy and failed/replayed chunks.
Run the affected host tests with `php artisan test --compact`; CSV adds no maintenance Artisan command.
Keep consumer DTOs, Actions, controllers and frontend types outside the package.
