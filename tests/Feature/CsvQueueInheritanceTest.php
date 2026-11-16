<?php

declare(strict_types=1);

use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Bus;
use Nvl\Csv\Jobs\ProcessCSVChunkJob;
use Nvl\Csv\Services\CSVAsyncProcessor;
use Nvl\Csv\Services\CSVHandlerRegistry;
use Nvl\Csv\Tests\Fixtures\OwnershipRowHandler;
use Nvl\Support\Doctor\DoctorRegistry;
use Nvl\Support\Providers\DoctorServiceProvider;

it('routes actual CSV batches through package Core or effective Laravel queue defaults', function (array $overrides, string $connection, string $queue): void {
    config([
        'nvl-csv.queue' => ['connection' => null, 'name' => null],
        'nvl-core.queue' => ['connection' => null, 'name' => null],
        'queue.default' => 'host', 'queue.connections.host.queue' => 'host-work', ...$overrides,
    ]);
    Bus::fake();
    $path = $this->temporaryCsv("id,name\n1,A\n");
    app(CSVHandlerRegistry::class)->register('rows', OwnershipRowHandler::class);
    app(CSVAsyncProcessor::class)->fromFile($path)->usingHandler('rows')->processAsync();
    Bus::assertBatched(static fn (PendingBatch $batch): bool => $batch->connection() === $connection
        && $batch->queue() === $queue
        && $batch->jobs->every(static fn (ProcessCSVChunkJob $job): bool => $job->connection === $connection && $job->queue === $queue));
})->with([
    'Laravel' => [[], 'host', 'host-work'],
    'Core' => [['nvl-core.queue' => ['connection' => 'suite', 'name' => 'suite-work']], 'suite', 'suite-work'],
    'package' => [['nvl-csv.queue' => ['connection' => 'csv', 'name' => 'csv-work']], 'csv', 'csv-work'],
    'explicit sync' => [['nvl-csv.queue' => ['connection' => 'sync', 'name' => 'inline']], 'sync', 'inline'],
]);

it('reports the effective staging disk, shared lock capability and queue retry timing', function (): void {
    $this->app->register(DoctorServiceProvider::class);
    config([
        'nvl-csv.work.disk' => 'missing',
        'nvl-csv.locks.store' => null,
        'nvl-core.locks.store' => 'array',
        'nvl-core.queue.connection' => 'database',
        'queue.connections.database.retry_after' => 360,
    ]);
    $checks = static fn () => collect(app(DoctorRegistry::class)->inspect()['checks'])->where('package', 'nvl/csv')->keyBy('key');
    expect($checks()->pluck('result', 'key')->all())->toMatchArray([
        'work.disk' => 'fail', 'locks.store' => 'fail', 'queue.retry_after' => 'fail',
    ]);

    config([
        'nvl-csv.work.disk' => 'local',
        'filesystems.disks.local.visibility' => 'private',
        'nvl-core.locks.store' => 'nvl_shared',
        'cache.stores.nvl_shared' => ['driver' => 'database', 'table' => 'cache', 'lock_table' => 'cache_locks'],
        'queue.connections.database.retry_after' => 361,
    ]);
    expect($checks()->pluck('result', 'key')->all())->toMatchArray([
        'work.disk' => 'pass', 'locks.store' => 'pass', 'queue.retry_after' => 'pass',
    ]);

    config(['filesystems.disks.local.read-only' => true]);
    expect($checks()['work.disk'])->toMatchArray(['severity' => 'error', 'result' => 'fail']);

    $unusedRoot = storage_path('csv-doctor-'.$this->name());
    config(['nvl-csv.work.disk' => 'new_staging', 'filesystems.disks.new_staging' => ['driver' => 'local', 'root' => $unusedRoot]]);
    expect($checks()['work.disk'])->toMatchArray(['result' => 'pass'])
        ->and(is_dir($unusedRoot))->toBeFalse();
});
