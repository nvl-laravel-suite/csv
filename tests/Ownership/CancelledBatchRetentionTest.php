<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Nvl\Csv\Services\CSVAsyncProcessor;
use Nvl\Csv\Services\CSVHandlerRegistry;
use Nvl\Csv\Services\CSVWorkStore;
use Nvl\Csv\Tests\OwnerScopeTestCase;
use Nvl\Ownership\Services\OwnershipRunner;
use Nvl\Ownership\Tests\Fixtures\Actor;
use Nvl\Ownership\Tests\Fixtures\Company;

uses(OwnerScopeTestCase::class);

it('retains unprocessed staged work after the deliveries of a cancelled batch drain', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'nvl_csv_');
    file_put_contents($path, "name\nFirst\nSecond\nThird\n");
    try {
        app(OwnershipRunner::class)->run(Company::query()->findOrFail(7), Actor::query()->findOrFail(1), function () use ($path): void {
            $processor = app(CSVAsyncProcessor::class);
            $batch = $processor->fromFile($path)->withChunkSize(2)->usingHandler('ownership-rows')->processAsync();
            $processor->cancelBatch($batch->id);

            foreach (DB::table('jobs')->orderBy('id')->get() as $queued) {
                $payload = json_decode($queued->payload, true, flags: JSON_THROW_ON_ERROR);
                $job = unserialize($payload['data']['command']);
                $job->handle(app(CSVWorkStore::class), app(CSVHandlerRegistry::class));
                $job->batch()->recordSuccessfulJob($payload['uuid']);
            }

            $directory = dirname($job->work->manifestPath);
            expect(DB::table('ownership_queue_probes')->where('stage', 'csv-row')->count())->toBe(0)
                ->and($processor->getBatchStatus($batch->id)['pending_jobs'])->toBe(0)
                ->and(Storage::disk('local')->exists($job->work->manifestPath))->toBeTrue()
                ->and(Storage::disk('local')->exists($directory.'/chunks/0.json'))->toBeTrue()
                ->and(Storage::disk('local')->exists($directory.'/chunks/1.json'))->toBeTrue();
        });
    } finally {
        unlink($path);
    }
});
