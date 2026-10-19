<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Nvl\Csv\Data\CSVImportOptionsData;
use Nvl\Csv\Services\CSVAsyncProcessor;
use Nvl\Csv\Tests\OwnerScopeTestCase;
use Nvl\Ownership\Services\OwnershipRunner;
use Nvl\Ownership\Tests\Fixtures\Actor;
use Nvl\Ownership\Tests\Fixtures\Company;
use Symfony\Component\Process\Process;

uses(OwnerScopeTestCase::class);

it('processes owned and global CSV batches with retry and private cleanup in an independent worker', function (): void {
    if (getenv('NVL_OWNERSHIP_TEST_DRIVER') !== 'pgsql') {
        $this->markTestSkipped('Requires the explicitly dedicated PostgreSQL source and independent worker.');
    }
    $batches = [];
    foreach ([[7, 'Retry row'], [8, 'Other owner'], [null, 'Global row']] as [$owner, $name]) {
        $path = tempnam(sys_get_temp_dir(), 'nvl_csv_');
        file_put_contents($path, "name\n".$name."\n");
        try {
            $process = fn () => app(CSVAsyncProcessor::class)->fromFile($path)->withOptions(CSVImportOptionsData::from(['filePath' => $path]))->usingHandler('ownership-rows')->processAsync();
            $batches[] = $owner === null ? $process() : app(OwnershipRunner::class)->run(Company::query()->findOrFail($owner), Actor::query()->findOrFail(1), $process);
        } finally {
            unlink($path);
        }
    }
    $worker = new Process([PHP_BINARY, __DIR__.'/../Fixtures/Ownership/worker.php', json_encode(config('database.connections.sqlite'), JSON_THROW_ON_ERROR), json_encode([
        'storage_root' => Storage::disk('local')->path(''), 'driver' => 'database',
    ], JSON_THROW_ON_ERROR)], timeout: 40);
    $worker->mustRun();
    expect($worker->getOutput())->toBe('csv-worker-complete')
        ->and(DB::table('jobs')->count())->toBe(0)
        ->and(DB::table('failed_jobs')->count())->toBe(0)
        ->and(DB::table('ownership_queue_probes')->where('stage', 'csv-row')->orderBy('label')->pluck('owner', 'label')->all())->toBe([
            'Global row' => 'unresolved', 'Other owner' => '8', 'Retry row' => '7',
        ])
        ->and(DB::table('ownership_queue_probes')->where('stage', 'csv-retry')->count())->toBe(1)
        ->and(DB::table('ownership_queue_probes')->where('stage', 'worker-after')->where('owner', '<>', 'unresolved')->count())->toBe(0)
        ->and(DB::table('job_batches')->where('pending_jobs', '<>', 0)->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles('ownership'))->toBe([]);
});
