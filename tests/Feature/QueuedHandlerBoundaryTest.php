<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Nvl\Csv\Services\CSVAsyncProcessor;
use Nvl\Support\Ownership\Exceptions\OwnershipViolation;

it('refuses arbitrary serialized queued callbacks before staging', function (): void {
    Storage::fake('local');
    expect(fn () => app(CSVAsyncProcessor::class)->processRow(static fn (array $row): null => null))
        ->toThrow(OwnershipViolation::class)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});
