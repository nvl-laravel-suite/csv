<?php

declare(strict_types=1);

use Illuminate\Log\LogManager;
use Nvl\Csv\Data\CSVImportOptionsData;
use Nvl\Csv\Jobs\ProcessCSVChunkJob;
use Nvl\Support\Facades\PackageLog;
use Nvl\Support\Logging\PackageLogger;
use Psr\Log\LoggerInterface;

it('emits one aggregate for failed rows in quiet mode and keeps row content private', function (): void {
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
    $job = new ProcessCSVChunkJob([
        ['row_number' => 1, 'data' => ['email' => 'first@private.test']],
        ['row_number' => 2, 'data' => ['email' => 'second@private.test']],
    ], 7, [], CSVImportOptionsData::from(['filePath' => 'private.csv']), static fn () => throw new RuntimeException('private secret row'));
    $job->handle();
    expect($records)->toBe([['warning', 'nvl.csv.chunk.rows_failed', ['chunk_index' => 7, 'processed_rows' => 0, 'failed_rows' => 2, 'package' => 'csv', 'message_key' => 'nvl.csv.chunk.rows_failed']]]);
});
