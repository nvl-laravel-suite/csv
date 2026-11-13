# Security Policy

Submit reports through [this package's private vulnerability reporting form](https://github.com/nvl-laravel-suite/csv/security/advisories/new).

Security fixes are provided for the published `7.x` release line. Earlier lines no longer receive fixes. Composer declares PHP `^8.4` and Laravel `^13.0`. The local Dagger release gate verifies PHP 8.4/Laravel 13 with SQLite and PostgreSQL 17 persistence contracts. PHP 8.5 requires separate compatibility evidence. Upstream security lifecycle limits still apply.

Report vulnerabilities privately through the repository host’s security-advisory feature. Include the affected version, filesystem/queue driver, source dialect and encoding, minimal reproduction, and impact. Remove confidential row values from the report unless they are essential to reproduce the issue.

CSV files are untrusted input. Consumers must authorize access, enforce upload and source-size policy, scan files where required, validate mappings before persistence, and avoid exposing retained failed-row data. Exports prefix formula-like text cells and headings with a single quote by default (`formula_policy` `escape`; `reject` refuses them). The explicit `allow` policy and files produced outside `CsvExporter` receive no such protection.

Queued work accepts registered `CSVRowHandler` classes only; closures and callback-bearing mappings are refused. Handlers must perform idempotent work. Keep the queue connection’s `retry_after` above the 360-second chunk lock lease and protect queue/batch metadata and the private work disk with the same operational access controls as other job infrastructure.
