<?php

declare(strict_types=1);

use Nvl\Csv\Ownership\CsvAdoptionAdapter;

it('declares no relational CSV resources for native ownership adoption', function (): void {
    expect(app(CsvAdoptionAdapter::class)->resources())->toBe([]);
});
