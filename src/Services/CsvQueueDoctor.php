<?php

declare(strict_types=1);

namespace Nvl\Csv\Services;

use Illuminate\Cache\CacheManager;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Config\Repository;
use Nvl\Csv\Jobs\ProcessCSVChunkJob;
use Nvl\Support\Config\PackageOptions;
use Nvl\Support\Doctor\DoctorCheck;
use Throwable;

/** @internal Reports the configured infrastructure used by queued CSV delivery. */
final readonly class CsvQueueDoctor
{
    public function __construct(private Repository $config, private CacheManager $cache) {}

    /** @return list<DoctorCheck> */
    public function inspect(): array
    {
        $connection = null;
        $driver = null;
        try {
            $connection = PackageOptions::queueConnection('csv');
            $driver = $this->config->get("queue.connections.{$connection}.driver");
        } catch (Throwable) {
            // Return the queue diagnostic beside the independent staging and lock checks.
        }

        return [$this->disk(), $this->locks($driver === 'sync'), $this->retryTiming($connection, $driver)];
    }

    private function disk(): DoctorCheck
    {
        $name = $this->config->get('nvl-csv.work.disk', 'local');
        $key = 'work.disk';
        if (! is_string($name) || trim($name) === '') {
            return new DoctorCheck($key, 'error', false, 'Set nvl-csv.work.disk to a configured private staging disk.');
        }
        $disk = $this->config->get('filesystems.disks.'.$name);
        if (! is_array($disk) || ! is_string($disk['driver'] ?? null) || $disk['driver'] === '') {
            return new DoctorCheck($key, 'error', false, "Configure the CSV staging disk [{$name}] in filesystems.disks.");
        }
        if (($disk['visibility'] ?? null) === 'public' || ($disk['read-only'] ?? false) === true) {
            return new DoctorCheck($key, 'error', false, "CSV staging disk [{$name}] must allow private writes.");
        }
        if ($disk['driver'] === 'local') {
            $root = $disk['root'] ?? null;
            if (! is_string($root) || trim($root) === '' || (file_exists($root) && (! is_dir($root) || ! is_writable($root)))) {
                return new DoctorCheck($key, 'error', false, "CSV staging disk [{$name}] requires a writable local root.");
            }
        }

        return new DoctorCheck($key, 'info', true, "CSV staging disk [{$name}] is configured for private staging. Producers and workers must share it and allow private writes.");
    }

    private function locks(bool $synchronous): DoctorCheck
    {
        $key = 'locks.store';
        try {
            $name = PackageOptions::lockStore('csv');
            $driver = $this->config->get("cache.stores.{$name}.driver");
            $repository = $this->cache->store($name);
        } catch (Throwable) {
            return new DoctorCheck($key, 'error', false, 'Set nvl-csv.locks.store to an initialized Laravel cache store with atomic locks.');
        }
        if (! $repository instanceof CacheRepository || ! $repository->getStore() instanceof LockProvider) {
            return new DoctorCheck($key, 'error', false, "CSV lock store [{$name}] does not support atomic locks.");
        }
        if ($driver === 'null' || ($driver === 'array' && ! $synchronous)) {
            return new DoctorCheck($key, 'error', false, "CSV lock store [{$name}] cannot coordinate the selected queue. Configure a shared atomic store.");
        }

        return new DoctorCheck($key, 'info', true, "CSV lock store [{$name}] provides atomic locks. Queue workers must use the same store.");
    }

    private function retryTiming(?string $connection, mixed $driver): DoctorCheck
    {
        $key = 'queue.retry_after';
        if ($connection === null || ! is_string($driver) || in_array($driver, ['', 'null'], true)) {
            return new DoctorCheck($key, 'error', false, 'Set nvl-csv.queue.connection to a configured queue that runs CSV jobs.');
        }
        if ($driver === 'sync') {
            return new DoctorCheck($key, 'info', true, "CSV queue [{$connection}] runs inline without reserved-job retries.");
        }

        $minimum = max(ProcessCSVChunkJob::TimeoutSeconds, ProcessCSVChunkJob::LockSeconds);
        $retryAfter = $this->config->get("queue.connections.{$connection}.retry_after");
        if ($driver === 'sqs' || ($retryAfter === null && ! in_array($driver, ['database', 'redis', 'beanstalkd'], true))) {
            return new DoctorCheck($key, 'warning', false, "Verify the broker visibility timeout for CSV queue [{$connection}] exceeds {$minimum} seconds.");
        }
        if (filter_var($retryAfter, FILTER_VALIDATE_INT, ['options' => ['min_range' => $minimum + 1]]) === false || is_bool($retryAfter)) {
            return new DoctorCheck($key, 'error', false, "Set queue.connections.{$connection}.retry_after above {$minimum} seconds for CSV jobs.");
        }

        return new DoctorCheck($key, 'info', true, "CSV queue [{$connection}] retries after the chunk timeout and lock lease.");
    }
}
