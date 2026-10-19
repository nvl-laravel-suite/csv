<?php

declare(strict_types=1);

namespace Nvl\Csv\Tests\Fixtures;

use Illuminate\Support\Facades\DB;
use Nvl\Csv\Contracts\CSVRowHandler;
use Nvl\Support\Ownership\Contracts\OwnershipContext;

/** Writes fixture effects using only the worker's admitted owner facts. */
final readonly class OwnershipRowHandler implements CSVRowHandler
{
    public function __construct(private OwnershipContext $context) {}

    /** @param array<string,mixed> $row */
    public function process(array $row, int $rowNumber): void
    {
        if (($row['name'] ?? null) === 'Retry row' && ! DB::table('ownership_queue_probes')->where('stage', 'csv-retry')->exists()) {
            DB::table('ownership_queue_probes')->insert(['stage' => 'csv-retry', 'owner' => $this->context->requireOwner()->key, 'label' => 'retry']);
            throw new \RuntimeException('Retry this native CSV chunk once.');
        }
        DB::table('ownership_queue_probes')->insert(['stage' => 'csv-row', 'owner' => $this->context->snapshot()->owner?->key ?? $this->context->snapshot()->mode->value, 'label' => $row['name']]);
    }
}
