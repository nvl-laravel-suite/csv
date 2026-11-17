<?php

declare(strict_types=1);

use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Nvl\Csv\Enums\CSVDuplicateStrategyEnum;
use Nvl\Csv\Enums\CSVEncodingEnum;
use Nvl\Csv\Enums\CSVProcessingModeEnum;
use Nvl\Csv\Enums\CSVTypeEnum;
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
                ->and(serialize($job))->not->toContain('Jane')->not->toContain('SerializableClosure');
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

it('enforces fluent mapping uniqueness across queued chunks', function (): void {
    Bus::fake();
    $path = $this->temporaryCsv("Email,Name\nada@example.test,Ada\nbob@example.test,Bob\nada@example.test,Ada Again\n");
    $queue = fn (string $strategy, ?CSVFieldMapping $required = null): CSVAsyncProcessor => tap(
        app(CSVAsyncProcessor::class)->fromFile($path)->withChunkSize(1)
            ->withOptions(['duplicate_strategy' => $strategy])
            ->mapField('Email', 'email', new CSVFieldMapping('Email', 'email', CSVTypeEnum::EMAIL, required: true, unique: true))
            ->mapField('Name', 'name')
            ->usingHandler('rows'),
        static fn (CSVAsyncProcessor $processor) => $required === null ? null : $processor->mapField($required->sourceField, $required->targetField, $required),
    );

    expect(fn () => $queue('error')->processAsync())->toThrow(CSVValidationException::class, 'Duplicate value')
        ->and(Storage::disk('local')->allFiles())->toBe([])
        ->and(fn () => $queue('skip', new CSVFieldMapping('Phone', 'phone', required: true))->processAsync())
        ->toThrow(CSVValidationException::class)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
    Bus::assertNothingDispatched();

    $queue('skip')->processAsync();
    $manifestPath = collect(Storage::disk('local')->allFiles('ownership'))->first(static fn (string $file): bool => str_ends_with($file, '/manifest.json'));
    $manifest = Storage::disk('local')->json($manifestPath);
    $rows = collect($manifest['manifest']['chunks'])->flatMap(static fn (array $chunk): array => Storage::disk('local')->json($chunk['path']))->all();

    expect($manifest['version'])->toBe(3)
        ->and($rows)->toBe([
            ['row_number' => 1, 'data' => ['email' => 'ada@example.test', 'name' => 'Ada']],
            ['row_number' => 2, 'data' => ['email' => 'bob@example.test', 'name' => 'Bob']],
        ]);
});

it('splits queued chunks by encoded byte budget', function (): void {
    Bus::fake();
    $payload = str_repeat('é', 3 * 512 * 1024);
    $path = $this->temporaryCsv("id,payload\n1,{$payload}\n2,{$payload}\n3,{$payload}\n");

    $batch = app(CSVAsyncProcessor::class)->fromFile($path)->usingHandler('rows')->processAsync();
    $manifestPath = collect(Storage::disk('local')->allFiles('ownership'))->first(static fn (string $file): bool => str_ends_with($file, '/manifest.json'));
    $chunks = Storage::disk('local')->json($manifestPath)['manifest']['chunks'];

    expect($batch->totalJobs)->toBe(2)
        ->and(array_column($chunks, 'rows'))->toBe([2, 1]);
    foreach ($chunks as $chunk) {
        $raw = Storage::disk('local')->get($chunk['path']);
        expect(strlen($raw))->toBeLessThanOrEqual(8388608)
            ->and(hash('sha256', $raw))->toBe($chunk['sha256'])
            ->and(json_decode($raw, true)[0]['data']['payload'])->toBe($payload);
    }
});

it('reports queued mapping failures with their source row and field', function (): void {
    Bus::fake();
    $path = $this->temporaryCsv("age\n42\nnot-an-integer\n");

    try {
        app(CSVAsyncProcessor::class)->fromFile($path)->usingHandler('rows')
            ->mapField('age', 'years', CSVFieldMapping::typed('age', 'years', CSVTypeEnum::INTEGER))->processAsync();
        $this->fail('Expected the queued mapping failure to surface from processAsync().');
    } catch (CSVValidationException $exception) {
        expect($exception->getMessage())->toBe("Field 'age' must be of type integer")
            ->and($exception->getAffectedRows())->toBe([2])
            ->and($exception->getRowErrors(2))->toBe(['age' => ["Field 'age' must be of type integer"]]);
    }

    expect(Storage::disk('local')->allFiles())->toBe([]);
    Bus::assertNothingDispatched();
});
