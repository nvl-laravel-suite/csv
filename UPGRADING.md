# Upgrading NVL CSV

## Consumer contracts, committed events and runtime policy (5.x)

Prefer focused public interfaces in constructor injection; native implementations remain container defaults and host prebindings win. Returned models are documented identity/data handles: use package contracts for reads/writes and capability-specific batch readers instead of direct package queries. Enable the shipped Core PHPStan include in your host; do not invoke the suite workbench static audit command in a consumer.

Events now carry immutable schemaVersion=1 and scalar/DTO snapshots. Replace model-bearing event fields with the IDs listed in [events](docs/events.md); load only through an authorized public reader when needed. Only six declared legacy `*Event` names are retained as PHP aliases for major 5, removal no earlier than major 6. Migrate exact imports/listeners/fakes to canonical names, replace suffix wildcard patterns explicitly, drain old queued payloads, rebuild event caches and restart workers. Framework Verified/PasswordReset remain native classes. Source-connection callbacks are process-local after-commit publication, not a durable outbox or exactly-once delivery.

Package failures have a marker and optional response metadata. Opt into Core's JSON renderer deliberately; preserve existing host handlers and request-locale selection. Missing required host adapters produce `binding_required`/500; genuine configured authorization denial retains native handling. See the README error table and required-bindings section where applicable.

Factories ship in runtime package mappings for host tests. Ordinary make may persist parents; withoutParents()->make creates detached fixtures. Supply persisted native owners/parents and active tenants explicitly, retain source revisions, and never treat a factory row as a real storage/provider/workflow effect. Core's optional installer publishes common config without enabling features; strict Doctor and explicit deployment cache/worker steps belong in the host release process. C3/C4/E executable acceptance is pending until recorded by integration.


## Tenant queue adoption

Drain legacy `csv-processing` manifests before tenancy activation. Register
stable handler aliases/classes, use `usingHandler()`, and keep handlers routed
through domain Actions. Disabled consumers retain the existing closure API.

## Standard CSV escaping

Defaults, format presets, and analyzer reads now disable PHP's proprietary
escape mechanism with an empty escape string. Embedded quotes use doubled
enclosures, preserving quotes and trailing backslashes across CSV round trips.

For a legacy import that intentionally uses backslash escaping, set
`new CSVConfiguration(escape: '\\')` or the equivalent explicit import DTO
option. Custom escape settings remain supported.

## Migrating from `App\Lib\CSV` to 1.0

Version 1.0 preserves the original class names and public fluent methods under the package namespace.

1. Replace `App\Lib\CSV\...` imports with `Nvl\Csv\...`.
2. Replace host `App\Traits\DataTransform` assumptions with the `Nvl\Data` DTO behavior now provided by `nvl/core`; no consumer trait import is required.
3. Keep `CSVFieldMapping::withTransformer(...)` as a static factory.
4. Confirm export paths. Fluent export builders default to the `exports` directory; directly constructed option DTOs without `path` write at the disk root.
5. Confirm duplicate behavior. `SKIP` and `ERROR` are enforced within the source file; persistence-oriented strategies pass duplicates to the application processor.
6. For async processing, create Laravel’s queue batch table, run workers for the effective CSV queue, and ensure captured callback state is serializable. Package `nvl-csv.queue.connection` and `nvl-csv.queue.name` overrides take priority, then Core queue defaults, then Laravel's selected connection and queue. Null inherits and explicit `sync` is preserved. Drain jobs queued under the old `csv-processing` default before restarting workers with the new effective queue.
7. Remove calls copied from the legacy prose documentation that never existed in the implementation, including instance `withTransformer`, `StringTransformer::make`, `lowercase` fluent methods, `nullIfEmpty`, `limit`, and importer `withDelimiter`.

The package now honors import/export DTO mappings and format/encoding options, resets reusable service state, handles variable-length BOMs, streams remote disks, and serializes queued callbacks. Strict imports reject uneven rows; lenient imports preserve the legacy pad/truncate behavior. Failure counters remain complete while only the first 1,000 failed-row payloads and error strings are retained. Test encoding, strictness, failure-reporting, and queue behavior when migrating workloads that depended on the previous accidental behavior.

## Tagged consumer PHP boundary

Use source `@api` workflows, extension contracts, and value types for application integration. Direct use of untagged implementations or `@internal` members is unsupported. This classification keeps existing concrete Action signatures and runtime behavior; it does not authorize package model persistence, ad hoc queries, relation traversal, or generic model serialization. Returned models are identity/result handles with only the explicitly declared in-memory read fields described in the README.

## Application workflow contracts

The four CSV service interfaces copy the native public instance methods (3 analyzer, 22 import, 12 export, 13 async). Mutable defaults remain transient; static factories and direct constructors remain callable.

The supported workflow injection names are `CSVAnalyzerContract`, `CSVImportContract`, `CSVExportContract`, `CSVAsyncProcessorContract`.

Inject these contracts when application workflows need substitution. Native
concrete constructors and operation signatures remain available through major 5;
internal workflow chains are unchanged. Register host implementations before
package discovery or replace the contract before resolving a new host service.
See [Testing your app](README.md#testing-your-app) for native fixtures and the
shipped consumer-audit PHPStan configuration.
