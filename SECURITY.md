# Security Policy

Submit reports through [this package's private vulnerability reporting form](https://github.com/nvl-laravel-suite/csv/security/advisories/new).

Security fixes are provided for the published `5.x` release line. Composer declares PHP `^8.4` and Laravel `^12.0|^13.0`. The local Dagger release gate verifies PHP 8.4/Laravel 13 with MySQL 8.4 and PostgreSQL 17 persistence contracts; PHP 8.5, Laravel 12 and MariaDB require separate compatibility evidence. Upstream security lifecycle limits still apply.

Report vulnerabilities privately through the repository host’s security-advisory feature. Include the affected version, filesystem/queue driver, source dialect and encoding, minimal reproduction, and impact. Remove confidential row values from the report unless they are essential to reproduce the issue.

CSV files are untrusted input. Consumers must authorize access, enforce upload and source-size policy, scan files where required, validate mappings before persistence, and avoid exposing retained failed-row data. The package does not neutralize spreadsheet formulas; apply an application-specific formula-injection policy before files are opened by spreadsheet software.

Async callbacks must capture only serializable state and perform idempotent work. Keep the queue connection’s `retry_after` above the job timeout and protect queue/batch metadata with the same operational access controls as other job infrastructure.
