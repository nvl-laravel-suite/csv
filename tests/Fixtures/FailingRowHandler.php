<?php

declare(strict_types=1);

namespace Nvl\Csv\Tests\Fixtures;

use Nvl\Csv\Contracts\CSVRowHandler;
use RuntimeException;

/** Simulates a host handler failure with private row data in its message. */
final class FailingRowHandler implements CSVRowHandler
{
    public function process(array $row, int $rowNumber): void
    {
        throw new RuntimeException('first@private.test');
    }
}
