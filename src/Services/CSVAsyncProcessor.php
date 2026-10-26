<?php

declare(strict_types=1);

namespace Nvl\Csv\Services;

use Closure;
use Illuminate\Bus\Batch;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Nvl\Csv\Enums\CSVDelimiterEnum;
use Nvl\Csv\Enums\CSVDuplicateStrategyEnum;
use Nvl\Csv\Enums\CSVEncodingEnum;
use Nvl\Csv\Enums\CSVProcessingModeEnum;
use Nvl\Csv\Enums\CSVTypeEnum;
use Nvl\Csv\Jobs\ProcessCSVChunkJob;
use Nvl\Csv\Ownership\CSVBatchCallbacks;
use Nvl\Csv\ValueObjects\CSVFieldMapping;
use Nvl\Csv\ValueObjects\CSVWorkReference;
use Nvl\Support\Config\PackageOptions;
use Nvl\Support\Ownership\Contracts\OwnershipQueueContext;
use Nvl\Support\Ownership\Enums\OwnershipContextMode;
use Nvl\Support\Ownership\Exceptions\OwnershipViolation;
use Nvl\Support\Ownership\Services\OwnershipContextResolver;
use Nvl\Support\Ownership\ValueObjects\OwnershipJobEnvelope;
use Throwable;

/**
 * Stages bounded private CSV chunks for a registered, durable row handler.
 *
 * @api
 */
final class CSVAsyncProcessor
{
    private ?string $filePath = null;

    /** @var array<string,mixed> */
    private array $options = [];

    private int $chunkSize = 1000;

    private ?string $handlerAlias = null;

    private int $handlerVersion = 1;

    /** @var array<string,CSVFieldMapping> */
    private array $fieldMappings = [];

    public function __construct(
        private readonly CSVWorkStore $workStore,
        private readonly CSVHandlerRegistry $handlers,
        private readonly OwnershipContextResolver $ownership,
        private readonly OwnershipQueueContext $ownershipQueue,
    ) {}

    public static function make(): self
    {
        return Container::getInstance()->make(self::class);
    }

    public function fromFile(string $path): self
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new InvalidArgumentException('CSV source must be a readable file.');
        }
        $this->filePath = $path;

        return $this;
    }

    /** @param array<string,mixed> $options */
    public function withOptions(array $options): self
    {
        $options = $this->normalizeOptions($options);
        $this->assertJsonOptions($options);
        (new CsvImporter)->withOptions($options);
        $this->options = $options;

        return $this;
    }

    public function withChunkSize(int $size): self
    {
        if ($size < 1 || $size > 100000) {
            throw new InvalidArgumentException('CSV chunk size must be between 1 and 100000.');
        }
        $this->chunkSize = $size;

        return $this;
    }

    public function usingHandler(string $alias, int $version = 1): self
    {
        if (! $this->handlers->has($alias, $version)) {
            throw new InvalidArgumentException("CSV handler [{$alias}] is not registered.");
        }
        $this->handlerAlias = $alias;
        $this->handlerVersion = $version;

        return $this;
    }

    public function mapField(string $source, string $target, ?CSVFieldMapping $mapping = null): self
    {
        $mapping ??= CSVFieldMapping::simple($source, $target);
        if ($mapping->transformer !== null || $mapping->validators !== []) {
            throw new OwnershipViolation('Queued CSV mappings cannot capture callbacks.');
        }
        $this->fieldMappings[$source] = $mapping;

        return $this;
    }

    /** Closures are synchronous only; queued work must use an allowlisted class handler. */
    public function processRow(Closure $processor): never
    {
        throw new OwnershipViolation('Queued CSV work requires a registered row handler.');
    }

    public function onProgress(Closure $callback): never
    {
        throw new OwnershipViolation('Queued CSV callbacks require a registered durable handler.');
    }

    public function onBatchComplete(Closure $callback): never
    {
        throw new OwnershipViolation('Queued CSV callbacks require a registered durable handler.');
    }

    public function onComplete(Closure $callback): never
    {
        throw new OwnershipViolation('Queued CSV callbacks require a registered durable handler.');
    }

    public function processAsync(): Batch
    {
        if ($this->filePath === null || $this->handlerAlias === null || ! $this->handlers->has($this->handlerAlias, $this->handlerVersion)) {
            throw new InvalidArgumentException('CSV source and registered handler are required before enqueueing.');
        }
        $snapshot = $this->ownership->current()->snapshot();
        $envelope = $snapshot->mode === OwnershipContextMode::Unresolved
            ? $this->ownershipQueue->captureUnowned()
            : OwnershipJobEnvelope::fromSnapshot($snapshot);

        return $this->ownershipQueue->run($envelope, function () use ($envelope): Batch {
            $disk = config('nvl-csv.work.disk', 'local');
            if (! is_string($disk) || $disk === '') {
                throw new OwnershipViolation('CSV work requires a configured private disk.');
            }
            $work = CSVWorkReference::forOwnership((string) Str::uuid(), $envelope, $disk, $this->handlerAlias ?? throw new OwnershipViolation, $this->handlerVersion);
            $jobs = $this->stage($work, $envelope);
            $batch = Bus::batch($jobs)->name('CSV Processing: '.$work->workId)->allowFailures()
                ->finally(CSVBatchCallbacks::cleanup($work))
                ->onConnection(PackageOptions::queueConnection('csv'))
                ->onQueue(PackageOptions::queueName('csv'));
            try {
                return $this->ownershipQueue->captureBatch($batch)->dispatch();
            } catch (Throwable $error) {
                $this->workStore->delete($work);
                throw $error;
            }
        });
    }

    /** @return array<string,mixed> */
    public function getBatchStatus(string $batchId): array
    {
        $batch = Bus::findBatch($batchId);
        if ($batch === null) {
            return ['status' => 'not_found'];
        }

        return ['id' => $batch->id, 'status' => $batch->cancelled() ? 'cancelled' : ($batch->finished() ? ($batch->hasFailures() ? 'completed_with_failures' : 'completed') : 'processing'),
            'processed_jobs' => $batch->processedJobs(), 'pending_jobs' => $batch->pendingJobs,
            'failed_jobs' => $batch->failedJobs, 'total_jobs' => $batch->totalJobs];
    }

    public function cancelBatch(string $batchId): bool
    {
        $batch = Bus::findBatch($batchId);
        if ($batch === null) {
            return false;
        }
        $batch->cancel();

        return true;
    }

    /** @return list<ProcessCSVChunkJob> */
    private function stage(CSVWorkReference $work, OwnershipJobEnvelope $envelope): array
    {
        $options = $this->options;
        $mappings = array_map(static fn (CSVFieldMapping $mapping): array => $mapping->toArray(), $this->fieldMappings);
        json_encode(['options' => $options, 'mappings' => $mappings], JSON_THROW_ON_ERROR);
        $importer = (new CsvImporter)->withOptions($options)->fromFile($this->filePath ?? throw new OwnershipViolation)->stopOnError();
        $chunks = [];
        $buffer = [];
        $index = 0;
        try {
            foreach ($importer->stream() as $rowNumber => $row) {
                $buffer[] = ['row_number' => $rowNumber, 'data' => $row];
                if (count($buffer) >= $this->chunkSize) {
                    $chunks[] = $this->writeChunk($work, $index++, $buffer);
                    $buffer = [];
                }
            }
            if ($buffer !== []) {
                $chunks[] = $this->writeChunk($work, $index, $buffer);
            }
            $checksum = $this->workStore->write($work, ['options' => $options, 'mappings' => $mappings, 'chunks' => $chunks]);
        } catch (Throwable $error) {
            Storage::disk($work->disk)->deleteDirectory(dirname($work->manifestPath));
            throw $error;
        }

        return array_map(static fn (array $chunk): ProcessCSVChunkJob => ProcessCSVChunkJob::fromOwnershipWork($work, $chunk['index'], $envelope, $checksum), $chunks);
    }

    /** @param list<array{row_number:int,data:array<string,mixed>}> $rows
     * @return array{index:int,path:string,sha256:string,rows:int}
     */
    private function writeChunk(CSVWorkReference $work, int $index, array $rows): array
    {
        $path = dirname($work->manifestPath).'/chunks/'.$index.'.json';
        $payload = json_encode($rows, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (strlen($payload) > 8388608 || ! Storage::disk($work->disk)->put($path, $payload, ['visibility' => 'private'])) {
            throw new OwnershipViolation('CSV chunk exceeds the private storage bound or cannot be staged.');
        }

        return ['index' => $index, 'path' => $path, 'sha256' => hash('sha256', $payload), 'rows' => count($rows)];
    }

    private function assertJsonOptions(mixed $value, int $depth = 0): void
    {
        if ($depth > 32 || (! is_null($value) && ! is_scalar($value) && ! is_array($value))) {
            throw new OwnershipViolation('Queued CSV options accept bounded JSON values only.');
        }
        if (is_array($value)) {
            foreach ($value as $item) {
                $this->assertJsonOptions($item, $depth + 1);
            }
        }
    }

    /** @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    private function normalizeOptions(array $options): array
    {
        if (($options['delimiter'] ?? null) instanceof CSVDelimiterEnum) {
            $options['delimiter'] = $options['delimiter']->getCharacter();
        }
        foreach (['encoding' => CSVEncodingEnum::class, 'duplicate_strategy' => CSVDuplicateStrategyEnum::class, 'processing_mode' => CSVProcessingModeEnum::class] as $key => $enumClass) {
            if (($options[$key] ?? null) instanceof $enumClass) {
                $options[$key] = $options[$key]->value;
            }
        }
        if (is_array($options['column_types'] ?? null)) {
            foreach ($options['column_types'] as $field => $type) {
                if ($type instanceof CSVTypeEnum) {
                    $options['column_types'][$field] = $type->value;
                }
            }
        }

        return $options;
    }
}
