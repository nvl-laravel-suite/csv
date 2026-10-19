<?php

declare(strict_types=1);

namespace Nvl\Csv\Ownership;

use Illuminate\Support\Facades\Storage;
use Nvl\Csv\ValueObjects\CSVWorkReference;
use Nvl\Support\Ownership\Contracts\OwnershipQueueContext;
use Nvl\Support\Ownership\Enums\OwnershipContextMode;
use Nvl\Support\Ownership\Exceptions\OwnershipViolation;
use Nvl\Support\Ownership\Services\OwnershipContextResolver;
use Nvl\Support\Ownership\ValueObjects\OwnershipJobEnvelope;
use RuntimeException;

/** Protects private JSON manifests by exact producer boundary, canonical path and content identity. @internal */
final readonly class OwnershipCSVWorkStore
{
    public function __construct(private OwnershipContextResolver $context, private OwnershipQueueContext $queue) {}

    /** @param array<string,mixed> $manifest */
    public function write(CSVWorkReference $work, array $manifest): string
    {
        $this->assertReference($work);
        $this->assertJson($manifest);
        $payload = $this->identity($work, $manifest);
        $payload['checksum'] = $this->checksum($payload);
        $encoded = json_encode($payload, JSON_THROW_ON_ERROR);
        if (strlen($encoded) > 8388608 || ! Storage::disk($work->disk)->put($work->manifestPath, $encoded, ['visibility' => 'private'])) {
            throw new RuntimeException('Unable to persist the bounded private CSV manifest.');
        }

        return hash('sha256', $encoded);
    }

    /** @return array<string,mixed> */
    public function read(CSVWorkReference $work, ?string $expectedChecksum = null): array
    {
        $this->assertReference($work);
        $raw = Storage::disk($work->disk)->get($work->manifestPath);
        if (! is_string($raw) || strlen($raw) > 8388608) {
            throw new OwnershipViolation('CSV manifest is missing or exceeds its bounded representation.');
        }
        if ($expectedChecksum !== null && ! hash_equals($expectedChecksum, hash('sha256', $raw))) {
            throw new OwnershipViolation('The staged CSV manifest changed after producer capture.');
        }
        try {
            $payload = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new OwnershipViolation('CSV manifest is not valid JSON.');
        }
        if (! is_array($payload) || count($payload) !== 7 || ! is_array($payload['manifest'] ?? null) || ! is_string($payload['checksum'] ?? null)) {
            throw new OwnershipViolation('CSV manifest has an unsupported shape.');
        }
        $manifest = [];
        foreach ($payload['manifest'] as $key => $value) {
            if (! is_string($key)) {
                throw new OwnershipViolation('CSV manifests require named fields.');
            }
            $manifest[$key] = $value;
        }
        $identity = $this->identity($work, $manifest);
        if (! hash_equals($this->checksum($identity), $payload['checksum']) || [...$identity, 'checksum' => $payload['checksum']] !== $payload) {
            throw new OwnershipViolation('CSV manifest identity or checksum differs from the captured work.');
        }

        return $manifest;
    }

    /** Delete only an admitted canonical work directory after validating its manifest. */
    public function delete(CSVWorkReference $work): void
    {
        $this->read($work);
        Storage::disk($work->disk)->deleteDirectory(dirname($work->manifestPath));
    }

    /** Revalidate current execution; a work reference never admits its recorded authority. */
    private function assertReference(CSVWorkReference $work): void
    {
        $envelope = $work->ownership ?? throw new OwnershipViolation('Native CSV work requires explicit producer capture.');
        $snapshot = $this->context->current()->snapshot();
        $current = $snapshot->mode === OwnershipContextMode::Unresolved
            ? OwnershipJobEnvelope::unowned($snapshot->generations) : OwnershipJobEnvelope::fromSnapshot($snapshot);
        if ($current->toArray() !== $envelope->toArray() || $work->tenantId !== null
            || ! in_array($snapshot->mode, [OwnershipContextMode::Owner, OwnershipContextMode::Central, OwnershipContextMode::Unresolved], true)
            || preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/iD', $work->workId) !== 1
            || $work->manifestPath !== CSVWorkReference::ownershipPath($work->workId, $envelope)
            || $work->disk !== config('nvl-csv.work.disk', 'local') || $work->handlerAlias === '' || strlen($work->handlerAlias) > 191 || $work->handlerVersion < 1) {
            throw new OwnershipViolation('CSV work does not belong to the current producer boundary and private storage.');
        }
        $this->queue->run($envelope, static fn (): null => null);
    }

    /** @param array<string,mixed> $manifest
     * @return array{version:int,work_id:string,ownership:array<string,mixed>,handler_alias:string,handler_version:int,manifest:array<string,mixed>}
     */
    private function identity(CSVWorkReference $work, array $manifest): array
    {
        return ['version' => 2, 'work_id' => $work->workId, 'ownership' => ($work->ownership ?? throw new OwnershipViolation)->toArray(),
            'handler_alias' => $work->handlerAlias, 'handler_version' => $work->handlerVersion, 'manifest' => $manifest];
    }

    /** @param array<string,mixed> $payload */
    private function checksum(array $payload): string
    {
        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /** Refuse serialized objects and excessive JSON nesting before writing. */
    private function assertJson(mixed $value, int $depth = 0): void
    {
        if ($depth > 32 || (! is_null($value) && ! is_scalar($value) && ! is_array($value))) {
            throw new OwnershipViolation('CSV manifests accept bounded JSON values only.');
        }
        if (is_array($value)) {
            foreach ($value as $item) {
                $this->assertJson($item, $depth + 1);
            }
        }
    }
}
