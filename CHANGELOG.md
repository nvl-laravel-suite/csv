# Changelog


All notable changes to `nvl/csv` are documented here.

## [Unreleased]

## [7.0.3] - 2026-11-13

### Fixed

- Isolate row-import database errors in nested savepoints on PostgreSQL and verify source, batch, and database-queue connection transaction levels before enqueueing async CSV batches.

<!-- NVL GENERATED CHANGES START -->
### Changed

- harden package runtime safety, PostgreSQL portability and test performance
<!-- NVL GENERATED CHANGES END -->

## [7.0.2] - 2026-11-11

### Fixed

- Apply `trim_values` to imported data cells, not only headers, before validation, duplicate detection and empty-row skipping.
- Keep a source selected with `fromFile()`/`fromDisk()` when `withOptions()` is called afterwards, and validate a `file_path` option like `fromFile()` with a path-free failure.
- Keep an export destination selected with `disk()`, `path()` or `filename()` when `withOptions()` is called afterwards; the file was written to the `local` disk root or the call failed for a missing filename.
- Abort queued staging on any failed source row; `strict_mode` width mismatches were silently dropped from the staged chunks.
- Refuse to queue a source without data rows instead of creating a batch that never finishes and an orphaned manifest.
- Keep the staged work of a cancelled batch; it was deleted once the remaining deliveries drained without processing their rows.
- Reject relative, year-less and time-only text (`tomorrow`, `sat`, `2024`, `12:30`, `1.5`) as `DATE`/`DATETIME` cells and in `DateTransformer`, and non-finite `FLOAT` values such as `1e999`.
- Decode and encode `CSVEncodingEnum::UTF16`/`UTF32` without a byte order mark as UTF-16LE/UTF-32BE on every platform.
- Detect UTF-8 when the analyzer's detection sample ends inside a multibyte character, and describe a column from its first non-empty cell.
- Read decimal commas in `NumericTransformer` (`1.234,56`, `1,5`) instead of dropping the separator.
- Reject line breaks in `CSVFieldValidator::email()` and `phone()`, and stop treating `0` as empty in `CSVFilter::field()->isEmpty()`/`isNotEmpty()`.

<!-- NVL GENERATED CHANGES START -->
### Changed

- correct detection, coercion, export destinations and staging refusals
<!-- NVL GENERATED CHANGES END -->

## [7.0.1] - 2026-11-05

### Fixed

- Reject invalid local and disk analyzer sources before reading, using path-free native failures while retaining readable files and local file URIs.

<!-- NVL GENERATED CHANGES START -->
### Changed

- validate analyzer sources before reading
<!-- NVL GENERATED CHANGES END -->

## [7.0.0] - 2026-11-03

- Adopt the reviewed NVL 7 family for PHP 8.4+ and Laravel 13, with compatible required and optional NVL peers. Review the package upgrade guide before deployment.

### Changed

- Align NVL peers and consumer contracts on the coordinated 7.x line. Review [UPGRADING.md](UPGRADING.md) for adoption and application-owned integration requirements.

### Fixed

- Reject nonfile and unreadable local import sources before parsing with a path-free native failure; retain readable ordinary files and local file URIs.
- Serialize concurrent deliveries of the same captured chunk on the configured atomic lock store and recheck current admission before handler effects.
- Report queued mapping failures through row-aware `CSVValidationException` diagnostics while retaining failed work.

### Documentation

- Require shared lock storage and describe lock/retry timing, host idempotency, PII retention and scoped retirement of failed staging.

## [6.0.0] - 2026-10-20

### Changed

- Capture native ownership for queued imports/exports and deny source restoration outside admitted work. Preserve bounded processing, host handlers and quiet logging defaults.
- Align required/development NVL peers on `^6.0`, optional peer conflicts on major 6, and `dev-main` on `6.x-dev`. Review [UPGRADING.md](UPGRADING.md) before rollout.

## [5.0.1] - 2026-10-08

### Changed

- Correct published-family verification and adoption guidance for local Dagger CI; runtime contracts are unchanged.


## [5.0.0] - 2026-10-08

### Changed

- The four CSV service interfaces copy the native public instance methods (3 analyzer, 22 import, 12 export, 13 async). Mutable defaults remain transient; static factories and direct constructors remain callable. Document contract substitution and truthful host fixtures in Testing your app.
- Classify the supported consumer PHP surface with explicit source annotations and restrict package model handles to declared identity and in-memory read fields; preserve existing workflow behavior and concrete signatures.
- Adopt lockstep major 5 with required and development NVL peer floors of `^5.0`.
- Inherit queue connection and destination from package options, Core defaults and Laravel.
- Keep CSV processing behavior unchanged apart from infrastructure composition and the lockstep major.
- Review [UPGRADING.md](UPGRADING.md) before adopting the new names and infrastructure boundaries.

## [2.2.1] - 2026-09-26

### Documentation

- Clarify public support, contribution, and private security reporting paths.

## [2.2.0] - 2026-09-25

### Changed

- Prepare `nvl/csv` for independent Composer and Git publication; require `nvl/core` for shared Support and Data services.

## [2.0.1] - 2026-09-22

### Added

- Added scalar tenant work manifests, class-resolved handlers, captured queue
  envelopes, exact cleanup, and adoption readiness checks.

### Fixed

- Use empty proprietary escape defaults and presets so embedded quotes and trailing backslashes survive standard CSV round trips; analyzer reads use the same convention. Explicit legacy escape settings remain available.
- Preserve the first row’s inferred export field order across reordered or incomplete rows, and reset inferred fields between exports.
- Preserve the first headerless input row on non-seekable and decoded streams without attempting to rewind the input.

## [2.0.0] - 2026-08-29

### Changed

- Declared Laravel 13 and `nvl/data` 2.x compatibility while preserving the
  typed analysis, import, export, and queued-processing consumer API.

## [1.0.7] - 2026-08-22

### Fixed

- Added explicit import transaction connection selection so whole-import and
  batch rollback guarantees apply to non-default Laravel connections.

### Changed

- Aligned the documented runtime requirement with the PHP 8.4+ package
  baseline.
- Extracted connection-aware transaction ownership from the stateful importer
  into a focused internal runner without changing the fluent import API.

## [1.0.5] - 2026-08-12

### Changed

- Released unchanged under the suite's shared version.

## [1.0.2] - 2026-08-12

- Made `CSVExport::fromQuery()` generic over its concrete Eloquent model so
  invariant `Builder<ConsumerModel>` types pass maximum-level PHPStan.
- Excluded tests, static-analysis configuration, coverage output, and test-run
  caches from production release archives.
- Fixed nested transaction ownership, error-threshold handling, strict row-shape validation, and bounded failure diagnostics.
- Applied import DTO column mappings, types, validation policy, and caller metadata consistently.
- Corrected UTF-32 BOM detection, boolean and JSON cast/validation consistency, exact date validation, UTC ISO output, and legacy-encoding BOM behavior.
- Improved logical-record delimiter detection, sampled large-file quality scoring, inferred export headings, common PHP value serialization, and chunked-array memory use.

## [1.0.0] - 2026-08-08

- Ported the complete `App\Lib\CSV` callable surface to `Nvl\Csv`.
- Added typed analysis, options, progress, import, and export Data objects with generated TypeScript registration.
- Added local and Laravel-disk streaming, BOM-aware input decoding, consistently encoded output streams, validators, transformers, filters, field mappings, and result objects.
- Added operation-local duplicate policies and explicit transaction behavior.
- Added serializable, storage-staged Laravel batch processing with tracking and cancellation.
- Added strict static analysis and comprehensive Pest coverage for compatibility and edge cases.
