<?php

declare(strict_types=1);

use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Nvl\Csv\Jobs\ProcessCSVChunkJob;
use Nvl\Csv\Services\CSVAsyncProcessor;
use Nvl\Csv\Services\CSVHandlerRegistry;
use Nvl\Csv\Tests\Fixtures\OwnershipRowHandler;
use Nvl\Csv\ValueObjects\CSVFieldMapping;
use Nvl\Support\Ownership\Exceptions\OwnershipViolation;

beforeEach(function (): void {
    Storage::fake('local');
    app(CSVHandlerRegistry::class)->register('rows', OwnershipRowHandler::class);
});

it('rejects callback-bearing mappings before they enter a queued payload', function (): void {
    $mapping = CSVFieldMapping::withTransformer('name', 'display_name', fn (mixed $value): string => strtoupper((string) $value));
    expect(fn () => serialize($mapping))->toThrow(InvalidArgumentException::class, 'cannot be serialized')
        ->and(fn () => app(CSVAsyncProcessor::class)->mapField('name', 'display_name', $mapping))
        ->toThrow(OwnershipViolation::class);
});

it('rejects unregistered handlers and arbitrary queued callbacks before staging', function (): void {
    $path = $this->temporaryCsv("name\nJane\n");
    expect(fn () => app(CSVAsyncProcessor::class)->usingHandler('missing'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => app(CSVAsyncProcessor::class)->processRow(static function (): void {}))
        ->toThrow(OwnershipViolation::class)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

it('rejects non JSON queued options at configuration', function (): void {
    expect(fn () => app(CSVAsyncProcessor::class)->withOptions([
        'metadata' => ['callback' => static fn (): null => null],
    ]))->toThrow(OwnershipViolation::class)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

it('stages bounded chunks instead of embedding source rows in jobs', function (): void {
    Bus::fake();
    $path = $this->temporaryCsv("id,name\n1,A\n2,B\n3,C\n");
    $batch = app(CSVAsyncProcessor::class)->fromFile($path)->withChunkSize(2)->usingHandler('rows')->processAsync();

    expect($batch->totalJobs)->toBe(2)
        ->and(Storage::disk('local')->allFiles('ownership'))->toHaveCount(3);
    Bus::assertBatched(function (PendingBatch $pending): bool {
        expect($pending->jobs)->toHaveCount(2);
        foreach ($pending->jobs as $job) {
            expect($job)->toBeInstanceOf(ProcessCSVChunkJob::class)
                ->and(serialize($job))->not->toContain('Jane', 'SerializableClosure');
        }

        return true;
    });
});

it('tracks and cancels fake batches through the public API', function (): void {
    Bus::fake();
    $path = $this->temporaryCsv("id\n1\n");
    $processor = app(CSVAsyncProcessor::class)->fromFile($path)->usingHandler('rows');
    expect($processor->getBatchStatus('missing'))->toBe(['status' => 'not_found'])
        ->and($processor->cancelBatch('missing'))->toBeFalse();
    $batch = $processor->processAsync();
    expect($processor->getBatchStatus($batch->id)['total_jobs'])->toBe(1)
        ->and($processor->cancelBatch($batch->id))->toBeTrue()
        ->and($processor->getBatchStatus($batch->id)['status'])->toBe('cancelled');
});

it('validates asynchronous configuration before dispatch', function (): void {
    expect(fn () => app(CSVAsyncProcessor::class)->withChunkSize(0))->toThrow(InvalidArgumentException::class)
        ->and(fn () => app(CSVAsyncProcessor::class)->processAsync())->toThrow(InvalidArgumentException::class, 'source and registered handler');
});
