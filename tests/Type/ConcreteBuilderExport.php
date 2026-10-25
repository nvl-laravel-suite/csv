<?php

declare(strict_types=1);

namespace Nvl\Csv\Tests\Type;

use Illuminate\Database\Eloquent\Builder;
use Nvl\Csv\Services\CsvExporter;
use Nvl\Csv\Tests\Fixtures\CsvExportRecord;

/**
 * Proves concrete model builders remain accepted without erasing their template type.
 *
 * @param  Builder<CsvExportRecord>  $records
 * @return array<string,mixed>
 */
function exportConcreteRecords(CsvExporter $export, Builder $records): array
{
    return $export->fromQuery($records);
}
