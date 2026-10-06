<?php

declare(strict_types=1);

use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Bus;
use Nvl\Csv\Data\CSVImportOptionsData;
use Nvl\Csv\Jobs\ProcessCSVChunkJob;
use Nvl\Csv\Services\CSVAsyncProcessor;

it('routes actual CSV batches through package Core or effective Laravel queue defaults', function (array $overrides, string $connection, string $queue): void {
    config([
        'nvl-csv.queue' => ['connection' => null, 'name' => null],
        'nvl-core.queue' => ['connection' => null, 'name' => null],
        'queue.default' => 'host', 'queue.connections.host.queue' => 'host-work', ...$overrides,
    ]);
    Bus::fake();
    $path = $this->temporaryCsv("id,name\n1,A\n");
    app(CSVAsyncProcessor::class)->fromFile($path)->withOptions(CSVImportOptionsData::from(['filePath' => $path]))->processAsync();
    Bus::assertBatched(static fn (PendingBatch $batch): bool => $batch->connection() === $connection
        && $batch->queue() === $queue
        && $batch->jobs->every(static fn (ProcessCSVChunkJob $job): bool => $job->connection === $connection && $job->queue === $queue));
})->with([
    'Laravel' => [[], 'host', 'host-work'],
    'Core' => [['nvl-core.queue' => ['connection' => 'suite', 'name' => 'suite-work']], 'suite', 'suite-work'],
    'package' => [['nvl-csv.queue' => ['connection' => 'csv', 'name' => 'csv-work']], 'csv', 'csv-work'],
    'explicit sync' => [['nvl-csv.queue' => ['connection' => 'sync', 'name' => 'inline']], 'sync', 'inline'],
]);
