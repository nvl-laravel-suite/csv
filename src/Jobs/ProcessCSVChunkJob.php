<?php

declare(strict_types=1);

namespace Nvl\Csv\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Nvl\Csv\Enums\CSVTypeEnum;
use Nvl\Csv\Services\CSVHandlerRegistry;
use Nvl\Csv\Services\CSVWorkStore;
use Nvl\Csv\ValueObjects\CSVFieldMapping;
use Nvl\Csv\ValueObjects\CSVWorkReference;
use Nvl\Support\Config\PackageOptions;
use Nvl\Support\Facades\PackageLog;
use Nvl\Support\Ownership\Contracts\OwnershipQueuedJob;
use Nvl\Support\Ownership\Exceptions\OwnershipViolation;
use Nvl\Support\Ownership\ValueObjects\OwnershipJobEnvelope;
use RuntimeException;
use Throwable;

/** A scalar reference to staged, sealed CSV work. The registered handler owns row side effects. */
final class ProcessCSVChunkJob implements OwnershipQueuedJob, ShouldQueue
{
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    /** @var list<int> */
    public array $backoff = [1, 5, 15];

    private function __construct(
        public readonly CSVWorkReference $work,
        public readonly int $chunkIndex,
        private readonly OwnershipJobEnvelope $ownership,
        private readonly string $manifestChecksum,
    ) {
        if ($work->ownership->toArray() !== $ownership->toArray() || preg_match('/^[a-f0-9]{64}$/D', $manifestChecksum) !== 1 || $chunkIndex < 0) {
            throw new OwnershipViolation('CSV job and work must share the exact captured boundary.');
        }
        $this->onConnection(PackageOptions::queueConnection('csv'));
        $this->onQueue(PackageOptions::queueName('csv'));
    }

    public static function fromOwnershipWork(CSVWorkReference $work, int $chunkIndex, OwnershipJobEnvelope $ownership, string $manifestChecksum): self
    {
        return new self($work, $chunkIndex, $ownership, $manifestChecksum);
    }

    public function ownershipJobEnvelope(): OwnershipJobEnvelope
    {
        return $this->ownership;
    }

    /** Validate the manifest and chunk before resolving the host's registered handler. */
    public function handle(CSVWorkStore $workStore, CSVHandlerRegistry $handlers): void
    {
        $manifest = $workStore->read($this->work, $this->manifestChecksum);
        $chunks = $manifest['chunks'] ?? null;
        $options = $manifest['options'] ?? null;
        $mappings = $manifest['mappings'] ?? null;
        if (! is_array($chunks) || ! is_array($options) || ! is_array($mappings)) {
            throw new OwnershipViolation('CSV work manifest shape is invalid.');
        }
        $chunk = $chunks[$this->chunkIndex] ?? null;
        $path = dirname($this->work->manifestPath).'/chunks/'.$this->chunkIndex.'.json';
        if (! is_array($chunk) || ($chunk['index'] ?? null) !== $this->chunkIndex || ($chunk['path'] ?? null) !== $path || ! is_string($chunk['sha256'] ?? null)) {
            throw new OwnershipViolation('CSV chunk reference is invalid.');
        }
        $done = $path.'.done';
        if (Storage::disk($this->work->disk)->exists($done)) {
            if (Storage::disk($this->work->disk)->get($done) !== $chunk['sha256']) {
                throw new OwnershipViolation('CSV chunk completion seal is invalid.');
            }

            return;
        }
        $raw = Storage::disk($this->work->disk)->get($path);
        if (! is_string($raw) || strlen($raw) > 8388608 || ! hash_equals($chunk['sha256'], hash('sha256', $raw))) {
            throw new OwnershipViolation('CSV chunk content or checksum is invalid.');
        }
        $rows = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
        if (! is_array($rows) || count($rows) !== $chunk['rows']) {
            throw new OwnershipViolation('CSV chunk row count is invalid.');
        }
        $handler = $handlers->resolve($this->work->handlerAlias, $this->work->handlerVersion);
        $fieldMappings = $this->restoreMappings($mappings);
        foreach ($rows as $row) {
            if ($this->batch()?->cancelled()) {
                return;
            }
            if (! is_array($row) || ! is_int($row['row_number'] ?? null) || ! is_array($row['data'] ?? null)) {
                throw new OwnershipViolation('CSV chunk row is invalid.');
            }
            $data = [];
            foreach ($row['data'] as $key => $value) {
                if (! is_string($key)) {
                    throw new OwnershipViolation('CSV row fields must be named.');
                }
                $data[$key] = $value;
            }
            if ($fieldMappings !== []) {
                $data = [];
                foreach ($fieldMappings as $source => $mapping) {
                    $value = $row['data'][$source] ?? $mapping->defaultValue;
                    if (($options['validate_data'] ?? true) && ! $mapping->validate($value)) {
                        throw new OwnershipViolation(implode(', ', $mapping->getValidationErrors($value)));
                    }
                    $data[$mapping->targetField] = $mapping->apply($value);
                }
            }
            try {
                $handler->process($data, $row['row_number']);
            } catch (Throwable $error) {
                PackageLog::log('csv', 'error', 'nvl.csv.chunk.failed', [
                    'chunk_index' => $this->chunkIndex,
                    'exception' => $error::class,
                ], 'quiet');
                throw $error;
            }
        }
        if (! Storage::disk($this->work->disk)->put($done, $chunk['sha256'], ['visibility' => 'private'])) {
            throw new RuntimeException('CSV chunk completion could not be recorded.');
        }
        Storage::disk($this->work->disk)->delete($path);
    }

    /** @param array<array-key,mixed> $rows
     * @return array<string,CSVFieldMapping>
     */
    private function restoreMappings(array $rows): array
    {
        $restored = [];
        foreach ($rows as $row) {
            if (! is_array($row) || ! is_string($row['source_field'] ?? null) || ! is_string($row['target_field'] ?? null)
                || ($row['has_transformer'] ?? false) !== false || ($row['validators_count'] ?? 0) !== 0
                || ! is_bool($row['required'] ?? null) || ! is_bool($row['unique'] ?? null)
                || ! is_bool($row['nullable'] ?? null) || ! is_array($row['metadata'] ?? null)
                || ! is_string($row['format'] ?? null) && ($row['format'] ?? null) !== null) {
                throw new OwnershipViolation('CSV field mapping is invalid.');
            }
            $metadata = [];
            foreach ($row['metadata'] as $key => $value) {
                if (! is_string($key)) {
                    throw new OwnershipViolation('CSV mapping metadata requires named fields.');
                }
                $metadata[$key] = $value;
            }
            $typeValue = $row['type'] ?? null;
            $type = is_string($typeValue) ? CSVTypeEnum::tryFrom($typeValue) : null;
            if ($typeValue !== null && $type === null) {
                throw new OwnershipViolation('CSV field mapping type is invalid.');
            }
            $mapping = new CSVFieldMapping(
                sourceField: $row['source_field'], targetField: $row['target_field'], type: $type,
                required: $row['required'], defaultValue: $row['default_value'] ?? null,
                unique: $row['unique'], nullable: $row['nullable'],
                format: $row['format'] ?? null, metadata: $metadata,
            );
            $restored[$mapping->sourceField] = $mapping;
        }

        return $restored;
    }
}
