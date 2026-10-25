<?php

declare(strict_types=1);

use Illuminate\Bus\PendingBatch;
use Illuminate\Log\LogManager;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Nvl\Csv\Jobs\ProcessCSVChunkJob;
use Nvl\Csv\Services\CSVAsyncProcessor;
use Nvl\Csv\Services\CSVHandlerRegistry;
use Nvl\Csv\Services\CSVWorkStore;
use Nvl\Csv\Tests\Fixtures\FailingRowHandler;
use Nvl\Support\Facades\PackageLog;
use Nvl\Support\Logging\PackageLogger;
use Psr\Log\LoggerInterface;

it('logs a retryable handler failure without private row content', function (): void {
    config()->set('nvl-core.logging.packages.csv.verbosity', 'quiet');
    config()->set('logging.channels.nvl', ['driver' => 'single']);
    $records = [];
    $sink = Mockery::mock(LoggerInterface::class);
    $sink->shouldReceive('log')->andReturnUsing(function ($level, $key, $context) use (&$records): void {
        $records[] = [$level, $key, $context];
    });
    $manager = Mockery::mock(LogManager::class);
    $manager->shouldReceive('channel')->andReturn($sink);
    $this->app->instance(PackageLogger::class, new PackageLogger(config(), $manager));
    PackageLog::clearResolvedInstance(PackageLogger::class);
    Storage::fake('local');
    Bus::fake();
    app(CSVHandlerRegistry::class)->register('failing', FailingRowHandler::class);
    $path = $this->temporaryCsv("email\nfirst@private.test\n");
    app(CSVAsyncProcessor::class)->fromFile($path)->usingHandler('failing')->processAsync();
    $job = null;
    Bus::assertBatched(function (PendingBatch $pending) use (&$job): bool {
        $job = $pending->jobs->first();

        return true;
    });
    expect($job)->toBeInstanceOf(ProcessCSVChunkJob::class);
    expect(fn () => $job->handle(app(CSVWorkStore::class), app(CSVHandlerRegistry::class)))->toThrow(RuntimeException::class);
    expect($records)->toHaveCount(1)
        ->and($records[0][1])->toBe('nvl.csv.chunk.failed')
        ->and(json_encode($records, JSON_THROW_ON_ERROR))->not->toContain('first@private.test')
        ->and(Storage::disk('local')->allFiles('ownership'))->not->toBe([]);
});
