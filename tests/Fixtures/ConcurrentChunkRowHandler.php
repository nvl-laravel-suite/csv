<?php

declare(strict_types=1);

namespace Nvl\Csv\Tests\Fixtures;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\DB;
use Nvl\Csv\Contracts\CSVRowHandler;
use Nvl\Support\Ownership\Contracts\OwnershipContext;
use RuntimeException;

/** Records a committed host effect while the parent holds the competing deliveries open. */
final readonly class ConcurrentChunkRowHandler implements CSVRowHandler
{
    public function __construct(private Repository $config, private OwnershipContext $context) {}

    /** @param array<string, mixed> $row */
    public function process(array $row, int $rowNumber): void
    {
        $directory = $this->config->get('csv-chunk-race.directory');
        if (! is_string($directory)) {
            throw new RuntimeException('The CSV race directory is unavailable.');
        }
        DB::table('ownership_queue_probes')->insert(['stage' => 'csv-duplicate-effect', 'owner' => $this->context->requireOwner()->key, 'label' => $row['name']]);
        $deadline = microtime(true) + 15;
        while (! is_file($directory.'/release')) {
            if (microtime(true) >= $deadline) {
                throw new RuntimeException('The CSV host-effect barrier timed out.');
            }
            usleep(1000);
            clearstatcache();
        }
    }
}
