<?php

declare(strict_types=1);

namespace Nvl\Csv\Enums;

use Nvl\Support\Contracts\ResponseCode;

/** Stable public response discriminators for Csv.
 * @api
 */
enum CSVResponseCode: string implements ResponseCode
{
    case OperationFailed = 'operation_failed';
    case InvalidConfiguration = 'invalid_configuration';
    case MemoryLimitExceeded = 'memory_limit_exceeded';
    case FileNotFound = 'file_not_found';
    case InvalidCsvInput = 'invalid_csv_input';
    case CsvParseFailed = 'csv_parse_failed';
}
