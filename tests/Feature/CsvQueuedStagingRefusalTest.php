<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Nvl\Csv\Exceptions\CSVParseException;
use Nvl\Csv\Exceptions\CSVValidationException;
use Nvl\Csv\Services\CSVAsyncProcessor;
use Nvl\Csv\Services\CSVHandlerRegistry;
use Nvl\Csv\Tests\Fixtures\OwnershipRowHandler;

beforeEach(function (): void {
    Storage::fake('local');
    Bus::fake();
    app(CSVHandlerRegistry::class)->register('rows', OwnershipRowHandler::class);
});

it('refuses to queue a source whose rows fail parsing instead of silently dropping them', function (): void {
    $path = $this->temporaryCsv("id,name\n1,A\n2\n3,C\n");

    expect(fn () => app(CSVAsyncProcessor::class)->fromFile($path)->withOptions(['strict_mode' => true])
        ->usingHandler('rows')->processAsync())->toThrow(CSVParseException::class, 'Column count mismatch');
    expect(Storage::disk('local')->allFiles())->toBe([]);
    Bus::assertNothingDispatched();
});

it('refuses to queue a source without data rows and stages no work', function (string $contents): void {
    $path = $this->temporaryCsv($contents);

    expect(fn () => app(CSVAsyncProcessor::class)->fromFile($path)->usingHandler('rows')->processAsync())
        ->toThrow(CSVValidationException::class, 'no data rows');
    expect(Storage::disk('local')->allFiles())->toBe([]);
    Bus::assertNothingDispatched();
})->with(["id,name\n", "id,name\n,\n\n"]);
