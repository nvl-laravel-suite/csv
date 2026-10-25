<?php

declare(strict_types=1);

use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Nvl\Csv\Enums\CSVDuplicateStrategyEnum;
use Nvl\Csv\Enums\CSVEncodingEnum;
use Nvl\Csv\Enums\CSVProcessingModeEnum;
use Nvl\Csv\Exceptions\CSVConfigurationException;
use Nvl\Csv\Exceptions\CSVValidationException;
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

it('rejects unknown queued enum values before staging', function (): void {
    expect(fn () => app(CSVAsyncProcessor::class)->withOptions(['encoding' => 'not-an-encoding']))
        ->toThrow(CSVConfigurationException::class)
        ->and(fn () => app(CSVAsyncProcessor::class)->withOptions(['duplicate_strategy' => 'not-a-policy']))
        ->toThrow(CSVConfigurationException::class)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

it('stages a nondefault encoded source with canonical JSON enum option values', function (): void {
    Bus::fake();
    $path = $this->temporaryCsv(mb_convert_encoding("name\nAndré\n", 'Windows-1252', 'UTF-8'));

    app(CSVAsyncProcessor::class)->fromFile($path)->withOptions([
        'encoding' => CSVEncodingEnum::WINDOWS_1252,
        'processing_mode' => CSVProcessingModeEnum::STREAM,
    ])->usingHandler('rows')->processAsync();

    $manifestPath = collect(Storage::disk('local')->allFiles('ownership'))->first(static fn (string $file): bool => str_ends_with($file, '/manifest.json'));
    $manifest = Storage::disk('local')->json($manifestPath);
    $chunk = Storage::disk('local')->json($manifest['manifest']['chunks'][0]['path']);

    expect($manifest['manifest']['options']['encoding'])->toBe('Windows-1252')
        ->and($manifest['manifest']['options']['processing_mode'])->toBe('stream')
        ->and($chunk[0]['data']['name'])->toBe('André');
});

it('fails queued staging for the error duplicate policy without dispatching or retaining work', function (): void {
    Bus::fake();
    $path = $this->temporaryCsv("name\nAda\nAda\n");

    expect(fn () => app(CSVAsyncProcessor::class)->fromFile($path)->withOptions([
        'unique_fields' => ['name'],
        'duplicate_strategy' => CSVDuplicateStrategyEnum::ERROR,
    ])->usingHandler('rows')->processAsync())->toThrow(CSVValidationException::class, 'Duplicate value');
    expect(Storage::disk('local')->allFiles())->toBe([]);
    Bus::assertNothingDispatched();
});

it('applies a scalar duplicate skip policy to queued chunk data', function (): void {
    Bus::fake();
    $path = $this->temporaryCsv("name\nAda\nAda\n");

    app(CSVAsyncProcessor::class)->fromFile($path)->withOptions([
        'unique_fields' => ['name'],
        'duplicate_strategy' => 'skip',
    ])->usingHandler('rows')->processAsync();

    $manifestPath = collect(Storage::disk('local')->allFiles('ownership'))->first(static fn (string $file): bool => str_ends_with($file, '/manifest.json'));
    $manifest = Storage::disk('local')->json($manifestPath);
    $chunk = Storage::disk('local')->json($manifest['manifest']['chunks'][0]['path']);

    expect($manifest['manifest']['options']['duplicate_strategy'])->toBe('skip')
        ->and($chunk)->toHaveCount(1);
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
