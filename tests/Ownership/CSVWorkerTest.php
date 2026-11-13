<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Nvl\Csv\Enums\CSVTypeEnum;
use Nvl\Csv\Exceptions\CSVValidationException;
use Nvl\Csv\Services\CSVAsyncProcessor;
use Nvl\Csv\Tests\OwnerScopeTestCase;
use Nvl\Csv\ValueObjects\CSVFieldMapping;
use Nvl\Ownership\Services\OwnershipRunner;
use Nvl\Ownership\Tests\Fixtures\Actor;
use Nvl\Ownership\Tests\Fixtures\Company;
use Nvl\Ownership\Tests\Fixtures\NativeDatabaseSource;
use Symfony\Component\Process\Process;

uses(OwnerScopeTestCase::class);

it('retains invalid queued rows as ordinary validation failures without ownership quarantine', function (): void {
    if (getenv('NVL_OWNERSHIP_TEST_DRIVER') !== 'pgsql') {
        $this->markTestSkipped('Requires an explicitly dedicated native source and independent worker.');
    }
    $path = tempnam(sys_get_temp_dir(), 'nvl_csv_');
    file_put_contents($path, "age\nnot-an-integer\n");
    try {
        $batch = app(OwnershipRunner::class)->run(Company::findOrFail(7), Actor::findOrFail(1), fn () => app(CSVAsyncProcessor::class)->fromFile($path)->usingHandler('ownership-rows')
            ->mapField('age', 'years', CSVFieldMapping::typed('age', 'years', CSVTypeEnum::INTEGER))->processAsync());
        $queued = DB::table('jobs')->sole();
        $wire = json_decode($queued->payload, true, flags: JSON_THROW_ON_ERROR);
        $job = unserialize($wire['data']['command']);
        $job->tries = 1;
        $wire['maxTries'] = 1;
        $wire['data']['command'] = serialize($job);
        DB::table('jobs')->where('id', $queued->id)->update(['payload' => json_encode($wire, JSON_THROW_ON_ERROR)]);
        $worker = new Process([PHP_BINARY, __DIR__.'/../Fixtures/Ownership/worker.php', json_encode(NativeDatabaseSource::capture(DB::connection('sqlite')), JSON_THROW_ON_ERROR), json_encode([
            'storage_root' => Storage::disk('local')->path(''), 'driver' => 'database',
        ], JSON_THROW_ON_ERROR)], timeout: 40);
        $worker->mustRun();
        $failed = DB::table('failed_jobs')->sole();
        $recordedBatch = DB::table('job_batches')->where('id', $batch->id)->sole();
        expect($worker->getOutput())->toBe('csv-worker-complete')
            ->and($failed->exception)->toContain(CSVValidationException::class)->not->toContain('OwnershipViolation')
            ->and($recordedBatch->failed_jobs)->toBe(1)
            ->and(DB::table('jobs')->count())->toBe(0)
            ->and(DB::table('ownership_queue_probes')->where('stage', 'csv-row')->count())->toBe(0)
            ->and(Storage::disk('local')->exists($job->work->manifestPath))->toBeTrue()
            ->and(Storage::disk('local')->exists(dirname($job->work->manifestPath).'/chunks/0.json'))->toBeTrue()
            ->and(Storage::disk('local')->exists(dirname($job->work->manifestPath).'/chunks/0.json.done'))->toBeFalse();
    } finally {
        unlink($path);
    }
});

it('processes owned and global CSV batches with retry and private cleanup in an independent worker', function (): void {
    if (getenv('NVL_OWNERSHIP_TEST_DRIVER') !== 'pgsql') {
        $this->markTestSkipped('Requires the explicitly dedicated native source and independent worker.');
    }
    $batches = [];
    foreach ([[7, 'Retry row'], [8, 'Other owner'], [null, 'Global row']] as [$owner, $name]) {
        $path = tempnam(sys_get_temp_dir(), 'nvl_csv_');
        file_put_contents($path, "name\n".$name."\n");
        try {
            $process = fn () => app(CSVAsyncProcessor::class)->fromFile($path)->usingHandler('ownership-rows')->processAsync();
            $batches[] = $owner === null ? $process() : app(OwnershipRunner::class)->run(Company::query()->findOrFail($owner), Actor::query()->findOrFail(1), $process);
        } finally {
            unlink($path);
        }
    }
    $worker = new Process([PHP_BINARY, __DIR__.'/../Fixtures/Ownership/worker.php', json_encode(NativeDatabaseSource::capture(DB::connection('sqlite')), JSON_THROW_ON_ERROR), json_encode([
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
