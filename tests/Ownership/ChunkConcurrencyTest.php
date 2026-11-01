<?php

declare(strict_types=1);

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Nvl\Csv\Services\CSVAsyncProcessor;
use Nvl\Csv\Services\CSVHandlerRegistry;
use Nvl\Csv\Services\CSVWorkStore;
use Nvl\Csv\Tests\Fixtures\ConcurrentChunkRowHandler;
use Nvl\Csv\Tests\Fixtures\ObservedChunkRedisStore;
use Nvl\Csv\Tests\OwnerScopeTestCase;
use Nvl\Ownership\Services\OwnershipRunner;
use Nvl\Ownership\Tests\Fixtures\Actor;
use Nvl\Ownership\Tests\Fixtures\Company;
use Nvl\Ownership\Tests\Fixtures\NativeDatabaseSource;
use Nvl\Support\Ownership\Contracts\OwnershipContext;
use Nvl\Support\Ownership\Contracts\OwnershipQueueContext;

uses(OwnerScopeTestCase::class);

it('serializes two native deliveries of one admitted chunk before host effects and completion', function (): void {
    if (! in_array(getenv('NVL_OWNERSHIP_TEST_DRIVER'), ['pgsql', 'mysql', 'mariadb'], true)
        || ! function_exists('pcntl_fork') || ! function_exists('pcntl_exec') || ! extension_loaded('redis')) {
        $this->markTestSkipped('Requires dedicated PostgreSQL/MySQL/MariaDB sources, pcntl and phpredis.');
    }
    $redisPort = getenv('NVL_OWNERSHIP_TEST_REDIS_PORT');
    if (! is_string($redisPort) || ! ctype_digit($redisPort)) {
        $this->markTestSkipped('Requires an explicitly dedicated Redis test port.');
    }
    $directory = sys_get_temp_dir().'/nvl-csv-chunk-race-'.Str::uuid();
    File::ensureDirectoryExists($directory);
    $prefix = 'nvl-csv-chunk-race:'.Str::uuid().':';
    config()->set([
        'csv-chunk-race.directory' => $directory,
        'nvl-csv.locks.store' => 'csv_chunk_race',
        'database.redis.client' => 'phpredis',
        'database.redis.options.prefix' => '',
        'database.redis.csv_chunks' => ['host' => '127.0.0.1', 'port' => (int) $redisPort, 'database' => 0],
        'cache.stores.csv_chunk_race' => ['driver' => 'csv_chunk_race'],
    ]);
    Cache::extend('csv_chunk_race', static fn (Application $app, array $config) => $app->make('cache')->repository(new ObservedChunkRedisStore($app->make('redis'), $directory, $prefix, 'csv_chunks')));
    app(CSVHandlerRegistry::class)->register('concurrent-rows', ConcurrentChunkRowHandler::class);
    $source = $directory.'/source.csv';
    file_put_contents($source, "name\nSingle host effect\n");
    app(OwnershipRunner::class)->run(Company::findOrFail(7), Actor::findOrFail(1), fn () => app(CSVAsyncProcessor::class)->fromFile($source)->usingHandler('concurrent-rows')->processAsync());
    $payload = json_decode(DB::table('jobs')->sole()->payload, true, flags: JSON_THROW_ON_ERROR);
    $job = unserialize($payload['data']['command']);
    $manifest = Storage::disk('local')->json($job->work->manifestPath);
    $chunk = $manifest['manifest']['chunks'][0];
    $parentSource = NativeDatabaseSource::inspect(DB::connection());
    foreach (DB::getConnections() as $connection) {
        $connection->disconnect();
    }
    $wait = static function (Closure $condition, string $message): void {
        $deadline = microtime(true) + 15;
        while (! $condition()) {
            if (microtime(true) >= $deadline) {
                throw new RuntimeException($message);
            }
            usleep(1000);
            clearstatcache();
        }
    };
    $children = [];
    try {
        foreach ([0, 1] as $index) {
            $pid = pcntl_fork();
            if ($pid === -1) {
                throw new RuntimeException('The CSV duplicate-delivery race could not fork.');
            }
            if ($pid > 0) {
                $children[] = $pid;

                continue;
            }
            try {
                app()->forgetScopedInstances();
                foreach (array_keys(DB::getConnections()) as $name) {
                    DB::purge($name);
                }
                app('redis')->purge('csv_chunks');
                Cache::forgetDriver('csv_chunk_race');
                $identity = NativeDatabaseSource::inspect(DB::connection(), $parentSource);
                file_put_contents($directory.'/ready-'.$index, 'ready');
                $wait(fn (): bool => is_file($directory.'/go'), 'The CSV worker start barrier timed out.');
                app(OwnershipQueueContext::class)->run($job->ownershipJobEnvelope(), fn () => $job->handle(app(CSVWorkStore::class), app(CSVHandlerRegistry::class)));
                $result = ['ok' => true, 'identity' => $identity, 'after' => app(OwnershipContext::class)->snapshot()->mode->value];
            } catch (Throwable $error) {
                $result = ['ok' => false, 'error' => $error::class, 'message' => $error->getMessage()];
            }
            file_put_contents($directory.'/result-'.$index.'.json', json_encode($result, JSON_THROW_ON_ERROR));
            pcntl_exec('/usr/bin/true');
            exit(1);
        }
        $wait(fn (): bool => is_file($directory.'/ready-0') && is_file($directory.'/ready-1'), 'Both independent CSV workers must be ready.');
        touch($directory.'/go');
        $wait(static function () use ($directory): bool {
            $effects = DB::table('ownership_queue_probes')->where('stage', 'csv-duplicate-effect')->count();

            return $effects >= 2 || $effects === 1 && count(glob($directory.'/lock-*.json') ?: []) === 2;
        }, 'Both CSV deliveries must reach either the real lock or the host-effect boundary.');
        $effectsAtBoundary = DB::table('ownership_queue_probes')->where('stage', 'csv-duplicate-effect')->count();
        $lockStates = array_map(static fn (string $path): array => json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR), glob($directory.'/lock-*.json') ?: []);
        touch($directory.'/release');
        foreach ($children as $pid) {
            pcntl_waitpid($pid, $status);
            expect(pcntl_wifexited($status))->toBeTrue()->and(pcntl_wexitstatus($status))->toBe(0);
        }
        $outcomes = array_map(static fn (int $index): array => json_decode(file_get_contents($directory.'/result-'.$index.'.json'), true, flags: JSON_THROW_ON_ERROR), [0, 1]);
        expect(array_column($outcomes, 'ok'))->toBe([true, true], json_encode($outcomes, JSON_THROW_ON_ERROR))
            ->and($effectsAtBoundary)->toBe(1)
            ->and(DB::table('ownership_queue_probes')->where('stage', 'csv-duplicate-effect')->get())->toHaveCount(1)
            ->and(DB::table('ownership_queue_probes')->where('stage', 'csv-duplicate-effect')->sole()->owner)->toBe('7')
            ->and(array_column($outcomes, 'after'))->toBe(['unresolved', 'unresolved'])
            ->and(array_unique(array_column(array_column($outcomes, 'identity'), 'process_id')))->toHaveCount(2)
            ->and(array_unique(array_column(array_column($outcomes, 'identity'), 'connection_id')))->toHaveCount(2)
            ->and(array_column($lockStates, 'acquired'))->toContain(true, false)
            ->and(array_unique(array_column($lockStates, 'redis_client_id')))->toHaveCount(2)
            ->and(Storage::disk('local')->get($chunk['path'].'.done'))->toBe($chunk['sha256'])
            ->and(Storage::disk('local')->exists($chunk['path']))->toBeFalse()
            ->and(Storage::disk('local')->exists($job->work->manifestPath))->toBeTrue();
    } finally {
        touch($directory.'/release');
        foreach ($children as $pid) {
            pcntl_waitpid($pid, $status);
        }
        foreach (glob($directory.'/lock-*.json') ?: [] as $path) {
            $state = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
            if (is_string($state['name'] ?? null) && str_starts_with($state['name'], $prefix)) {
                app('redis')->connection('csv_chunks')->del($state['name']);
            }
        }
        app('redis')->purge('csv_chunks');
        File::deleteDirectory($directory);
    }
});
