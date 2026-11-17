<?php

declare(strict_types=1);

namespace Nvl\Csv\Ownership;

use Closure;
use Illuminate\Bus\Batch;
use Illuminate\Container\Container;
use Nvl\Csv\Services\CSVWorkStore;
use Nvl\Csv\ValueObjects\CSVWorkReference;

/**
 * Resolves native completion callbacks after the worker re-admits their captured boundary.
 *
 * @internal
 */
final class CSVBatchCallbacks
{
    /** @var array<string,bool> Work IDs whose producer dispatch is in progress, mapped to whether it completed inline. */
    private static array $dispatching = [];

    /** Retain failed or cancelled chunks and remove only successfully completed temporary work.
     * @return Closure(Batch): void
     */
    public static function cleanup(CSVWorkReference $work): Closure
    {
        return static function (Batch $batch) use ($work): void {
            if ($batch->failedJobs !== 0 || $batch->cancelled()) {
                return;
            }
            // Inline completion defers removal until the producer's dispatch has returned.
            if (array_key_exists($work->workId, self::$dispatching)) {
                self::$dispatching[$work->workId] = true;

                return;
            }
            Container::getInstance()->make(CSVWorkStore::class)->delete($work);
        };
    }

    /** Mark the producer's dispatch of this work as in progress. */
    public static function beginDispatch(CSVWorkReference $work): void
    {
        self::$dispatching[$work->workId] = false;
    }

    /** End the producer's dispatch and report whether the work completed inline and awaits removal. */
    public static function endDispatch(CSVWorkReference $work): bool
    {
        $completed = self::$dispatching[$work->workId] ?? false;
        unset(self::$dispatching[$work->workId]);

        return $completed;
    }
}
