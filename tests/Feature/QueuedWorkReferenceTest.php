<?php

declare(strict_types=1);

use Nvl\Csv\Jobs\ProcessCSVChunkJob;
use Nvl\Csv\ValueObjects\CSVWorkReference;
use Nvl\Support\Ownership\Enums\OwnershipContextMode;
use Nvl\Support\Ownership\ValueObjects\OwnershipJobEnvelope;
use Nvl\Support\Ownership\ValueObjects\OwnershipSnapshot;

it('serializes only a captured scalar native work reference', function (): void {
    $envelope = OwnershipJobEnvelope::fromSnapshot(new OwnershipSnapshot(OwnershipContextMode::Disabled));
    $work = CSVWorkReference::forOwnership('11111111-1111-4111-8111-111111111111', $envelope, 'local', 'contacts', 1);
    $job = ProcessCSVChunkJob::fromOwnershipWork($work, 0, $envelope, str_repeat('a', 64));
    $payload = serialize($job);

    expect($payload)->not->toContain('rowProcessor', 'SerializableClosure', 'TenantContext')
        ->and(unserialize($payload))->toBeInstanceOf(ProcessCSVChunkJob::class);
});
