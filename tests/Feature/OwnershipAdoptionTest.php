<?php

declare(strict_types=1);

use Nvl\Csv\Ownership\CsvAdoptionAdapter;
use Nvl\Csv\Providers\CsvServiceProvider;
use Nvl\Ownership\Providers\OwnershipServiceProvider;
use Nvl\Ownership\Services\OwnershipAdoptionRegistry;

it('declares no relational CSV resources for native ownership adoption', function (): void {
    app()->register(OwnershipServiceProvider::class);
    app()->register(CsvServiceProvider::class, true);

    expect(app(OwnershipAdoptionRegistry::class)->all()['csv'] ?? null)->toBe(CsvAdoptionAdapter::class);
    expect(app(CsvAdoptionAdapter::class)->resources())->toBe([]);
});
