<?php

declare(strict_types=1);

namespace Nvl\Csv\Tests\Fixtures;

use Illuminate\Cache\PhpRedisLock;
use Illuminate\Cache\RedisStore;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Redis\Factory;
use Illuminate\Redis\Connections\PhpRedisConnection;
use InvalidArgumentException;

/** Observes real Redis acquisition attempts without replacing their atomic behavior. */
final class ObservedChunkRedisStore extends RedisStore
{
    public function __construct(Factory $redis, private readonly string $directory, string $prefix, string $connection)
    {
        parent::__construct($redis, $prefix, $connection);
    }

    public function lock(mixed $name, mixed $seconds = 0, mixed $owner = null): Lock
    {
        $connection = $this->lockConnection();
        if (! $connection instanceof PhpRedisConnection || ! is_string($name) || ! is_int($seconds) || ! is_string($owner) && $owner !== null) {
            throw new InvalidArgumentException('The native CSV race requires an actual phpredis lock.');
        }

        return new class($connection, $this->prefix.$name, $seconds, $owner, $this->directory) extends PhpRedisLock
        {
            public function __construct(PhpRedisConnection $redis, string $name, int $seconds, ?string $owner, private readonly string $directory)
            {
                parent::__construct($redis, $name, $seconds, $owner);
            }

            public function acquire(): bool
            {
                $acquired = parent::acquire();
                $path = $this->directory.'/lock-'.getmypid().'.json';
                file_put_contents($path.'.tmp', json_encode(['acquired' => $acquired, 'name' => $this->name, 'process_id' => getmypid(), 'redis_client_id' => $this->redis->command('client', ['ID'])], JSON_THROW_ON_ERROR));
                rename($path.'.tmp', $path);

                return $acquired;
            }
        };
    }
}
