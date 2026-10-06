<?php

declare(strict_types=1);

namespace Nvl\Csv\Contracts;

/**
 * Handle one parsed CSV row through a host-owned import callback.
 *
 * @api
 */
interface CSVRowHandler
{
    /** @param array<string,mixed> $row */
    public function process(array $row, int $rowNumber): void;
}
