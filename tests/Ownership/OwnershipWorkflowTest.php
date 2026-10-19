<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Nvl\Csv\Data\CSVImportOptionsData;
use Nvl\Csv\Services\CSVAsyncProcessor;
use Nvl\Csv\Services\CSVHandlerRegistry;
use Nvl\Csv\Services\CSVWorkStore;
use Nvl\Csv\Tests\OwnerScopeTestCase;
use Nvl\Csv\ValueObjects\CSVWorkReference;
use Nvl\Ownership\Services\OwnershipQueueContext;
use Nvl\Ownership\Services\OwnershipRunner;
use Nvl\Ownership\Tests\Fixtures\Actor;
use Nvl\Ownership\Tests\Fixtures\Company;
use Nvl\Support\Ownership\Contracts\OwnershipContext;
use Nvl\Support\Ownership\Exceptions\OwnershipViolation;
use Nvl\Support\Ownership\ValueObjects\OwnershipJobEnvelope;

uses(OwnerScopeTestCase::class);

it('stages captured private chunks and keeps status cancellation and manifests inside the native owner', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'nvl_csv_');
    file_put_contents($path, "name\nPrivate row\n");
    try {
        $batch = app(OwnershipRunner::class)->run(Company::query()->findOrFail(7), Actor::query()->findOrFail(1), function () use ($path) {
            return app(CSVAsyncProcessor::class)->fromFile($path)
                ->withOptions(CSVImportOptionsData::from(['filePath' => $path]))->usingHandler('ownership-rows')->processAsync();
        });
        $payload = json_decode(DB::table('jobs')->sole()->payload, true, flags: JSON_THROW_ON_ERROR);
        expect($payload['data']['nvl_ownership']['owner']['key'])->toBe('7')
            ->and($payload['data']['command'])->not->toContain('Private row', 'SerializableClosure');
        $job = unserialize($payload['data']['command']);
        app(OwnershipRunner::class)->run(Company::query()->findOrFail(8), Actor::query()->findOrFail(1), function () use ($batch, $job): void {
            expect(fn () => app(CSVAsyncProcessor::class)->getBatchStatus($batch->id))->toThrow(OwnershipViolation::class);
            expect(fn () => app(CSVAsyncProcessor::class)->cancelBatch($batch->id))->toThrow(OwnershipViolation::class);
            expect(fn () => $job->handle(app(CSVWorkStore::class), app(CSVHandlerRegistry::class)))->toThrow(OwnershipViolation::class);
        });
        app(OwnershipQueueContext::class)->run($job->ownershipJobEnvelope(), fn () => $job->handle(app(CSVWorkStore::class), app(CSVHandlerRegistry::class)));
        expect(DB::table('ownership_queue_probes')->where('stage', 'csv-row')->value('owner'))->toBe('7');
    } finally {
        unlink($path);
    }
});

it('rejects forged native manifest paths and foreign owner references before touching storage', function (): void {
    $actor = Actor::query()->findOrFail(1);
    $work = app(OwnershipRunner::class)->run(Company::query()->findOrFail(7), $actor, function () {
        $work = CSVWorkReference::forOwnership('11111111-1111-4111-8111-111111111111', OwnershipJobEnvelope::capture(app(OwnershipContext::class)), 'local', 'ownership-rows', 1);
        app(CSVWorkStore::class)->write($work, ['chunks' => []]);
        $forged = new CSVWorkReference($work->workId, null, $work->disk, '../outside/manifest.json', $work->handlerAlias, 1, $work->ownership);
        expect(fn () => app(CSVWorkStore::class)->write($forged, []))->toThrow(OwnershipViolation::class);

        return $work;
    });
    app(OwnershipRunner::class)->run(Company::query()->findOrFail(8), $actor, function () use ($work): void {
        expect(fn () => app(CSVWorkStore::class)->read($work))->toThrow(OwnershipViolation::class);
        expect(fn () => app(CSVWorkStore::class)->delete($work))->toThrow(OwnershipViolation::class);
    });
    expect(Storage::disk('local')->exists($work->manifestPath))->toBeTrue();
});

it('runs optional unowned CSV handlers in an explicit global frame without granting owner authority', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'nvl_csv_');
    file_put_contents($path, "name\nGlobal row\n");
    try {
        app(CSVAsyncProcessor::class)->fromFile($path)->withOptions(CSVImportOptionsData::from(['filePath' => $path]))->usingHandler('ownership-rows')->processAsync();
        $payload = json_decode(DB::table('jobs')->sole()->payload, true, flags: JSON_THROW_ON_ERROR);
        expect($payload['data']['nvl_ownership']['mode'])->toBe('unresolved');
        $job = unserialize($payload['data']['command']);
        app(OwnershipQueueContext::class)->run($job->ownershipJobEnvelope(), fn () => $job->handle(app(CSVWorkStore::class), app(CSVHandlerRegistry::class)));
        expect(DB::table('ownership_queue_probes')->where('stage', 'csv-row')->value('owner'))->toBe('unresolved');
    } finally {
        unlink($path);
    }
});

it('refuses stale handler versions and serialized callbacks before creating private work', function (): void {
    expect(fn () => app(CSVAsyncProcessor::class)->usingHandler('ownership-rows', 2))->toThrow(InvalidArgumentException::class);
    expect(fn () => app(CSVAsyncProcessor::class)->processRow(static fn (): null => null))->toThrow(OwnershipViolation::class);
    expect(Storage::disk('local')->allFiles())->toBe([]);
});

it('binds queued chunks to the original manifest bytes even if a stored checksum is recomputed', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'nvl_csv_');
    file_put_contents($path, "name\nOriginal row\n");
    try {
        app(OwnershipRunner::class)->run(Company::query()->findOrFail(7), Actor::query()->findOrFail(1), function () use ($path): void {
            app(CSVAsyncProcessor::class)->fromFile($path)->withOptions(CSVImportOptionsData::from(['filePath' => $path]))->usingHandler('ownership-rows')->processAsync();
            $payload = json_decode(DB::table('jobs')->sole()->payload, true, flags: JSON_THROW_ON_ERROR);
            $job = unserialize($payload['data']['command']);
            $manifestPath = array_values(array_filter(Storage::disk('local')->allFiles('ownership'), static fn (string $path): bool => str_ends_with($path, '/manifest.json')))[0];
            $manifest = Storage::disk('local')->json($manifestPath);
            $manifest['manifest']['chunks'][0]['rows'] = 999;
            unset($manifest['checksum']);
            $manifest['checksum'] = hash('sha256', json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            Storage::disk('local')->put($manifestPath, json_encode($manifest, JSON_THROW_ON_ERROR));
            expect(fn () => $job->handle(app(CSVWorkStore::class), app(CSVHandlerRegistry::class)))->toThrow(OwnershipViolation::class);
            expect(DB::table('ownership_queue_probes')->where('stage', 'csv-row')->count())->toBe(0);
        });
    } finally {
        unlink($path);
    }
});
