---
name: nvl-csv
description: Implement, integrate, test, or review nvl/csv in Laravel 13. Use for CSV analysis, typed import/export options, field mappings, validation, transformations, streaming, duplicate policies, memory limits, or queued chunk processing.
---

# NVL CSV

Treat CSV input as untrusted, potentially large data whose dialect, encoding, validation policy, and write boundary must be explicit.

Tenant async imports must use `usingHandler()` with a registered handler class.
Do not serialize closures or models; preserve the manifest's tenant/work/path
identity and let each handler call its owning domain Action.

## Import

- Configure a source with `CSVImport::make()->fromFile(...)` or `fromDisk(...)`.
- Express schema conversion with `CSVFieldMapping`; keep persistence in the row processor.
- Choose delimiter, encoding, headers, row limits, duplicate strategy, transaction behavior, and error thresholds deliberately. Defaults use standard doubled enclosures (`escape: ''`); choose an explicit backslash escape only for a legacy source that requires it.
- Use `stream()` for pull-based processing and `batch()` for bounded synchronous chunks.
- Do not retain complete source files or unbounded error payloads in memory.

## Export

- Configure exact headings and fields before exporting arrays, collections, or Eloquent queries. If omitted, the first row fixes field order for that export; later missing keys produce empty fields and extra keys are ignored.
- Prefer query chunking for large datasets.
- Choose format, delimiter, encoding, BOM, header, and index behavior through `CSVConfiguration` or `CSVExportOptionsData`.
- Keep exports on a configured Laravel filesystem disk; do not assume a local path.

## Async processing

- Use `CSVAsyncProcessor` only with serializable callbacks and a configured queue batch repository.
- Ensure the worker timeout is lower than the connection's `retry_after`.
- Treat batch status metadata as operational data, not durable business state.

## Verify

Test quoted delimiters, embedded newlines, BOMs, non-UTF-8 encodings, empty files, missing columns, validation failures, duplicate strategies, transaction rollbacks, non-seekable headerless disks, reordered export keys, large iterables, serialized jobs, cancellation, and failed batches.

## Configurable-tenancy release discipline

- Preserve disabled compatibility and package independence; tenant support never creates an undeclared Auth or Suite dependency.
- Use registered package-owned resources, adoption adapters, Actions, and lifecycle APIs. Never add a generic tenant delete-all path or raw cross-package cleanup.
- Treat mapping/configuration hashes, interruption checkpoints, conservation evidence, worker context, tenant-leading queries, and standalone consumption as release contracts.

## Canonical configuration ownership

- This package does not ship a package config file or package environment variables. Configure behavior through its typed APIs; do not invent a `nvl-csv` config root.
- When composing configured NVL packages, use their shipped canonical `nvl-<package>` roots and `NVL_<PACKAGE>_*` inputs. Core's generic config/environment compatibility is default off and applies only to explicitly selected historical inputs.
- Keep logical package/tenant resource identifiers unchanged and use the package's canonical skill publication tag. Preserve host global registrations.

## Application workflow substitution

The four CSV service interfaces copy the native public instance methods (3 analyzer, 22 import, 12 export, 13 async). Mutable defaults remain transient; static factories and direct constructors remain callable.

The supported workflow injection names are `CSVAnalyzerContract`, `CSVImportContract`, `CSVExportContract`, `CSVAsyncProcessorContract`.

Inject the supported contract into host orchestration and bind a native interface
mock or host implementation before resolving that orchestration. Keep concrete
constructors and native workflow bodies intact; internal chains remain package-owned.
Use declared DTOs or unsaved model identity handles for orchestration fixtures.
Use real package workflows and Laravel framework fakes for persistence, tenant,
queue, file, and external-effect integration checks. A host substitute proves
only the host call and result. Keep public declarations tagged `@api` and
constructor/configuration/private helpers internal.

Consult the owning README's Testing your app section for native examples. Include
`vendor/nvl/core/support/consumer-audit.neon` in host PHPStan and declare explicit
`nvlConsumer.testPaths`; the Suite workbench is not consumer tooling.


## Consumer runtime and testing contracts

Start with the package README Quickstart and Testing your app sections. Use `nvl:install <package>` for loaded-package common config publication; it does not enable features, run schema or refresh caches. Preserve native host owner keys/morph maps and selected auth/tenancy defaults. Read full runtime defaults and publish advanced config only deliberately.

Inject the supported focused interfaces and preserve host bindings. Returned model handles do not permit package-table queries/writes outside documented capability/extension seams. Host tests may substitute contracts in Laravel's container, use shipped model factories (ordinary make may persist parents; withoutParents()->make is detached), and use Laravel effect fakes deliberately. Only Media/Stripe have dedicated provider/library fakes; do not invent a universal package fake. Settings InteractsWithSettings is definition-only. Host PHPStan may include vendor/nvl/core/support/consumer-audit.neon; no unpublished workbench command is a consumer requirement.

Read docs/events.md and the package README error table. Domain events use schemaVersion=1, model-free facts and actual source-connection commit callbacks; only six declared old Event suffix aliases remain for major 5. Migrate exact listeners/fakes and suffix wildcards, drain old queued payloads, rebuild event cache and restart workers. Delivery is not a durable outbox. The Core exception renderer is opt-in, JSON-only for respondable failures, with exactly message/code/context and host-selected locale. Do not expose diagnostics or reinterpret missing bindings as authorization denial.

Core package logging uses nvl/normal with CSV quiet by default, stable message keys and bounded context; incidents survive quiet. Do not mutate global logger context or log raw row/provider/content/credential payloads. Run only authorized project checks and report new acceptance as pending until actual output exists.
