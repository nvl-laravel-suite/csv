<?php

declare(strict_types=1);

namespace Nvl\Csv\Ownership;

use Closure;
use Illuminate\Bus\Batch;
use Illuminate\Container\Container;
use Nvl\Csv\Services\CSVWorkStore;
use Nvl\Csv\ValueObjects\CSVWorkReference;

/** Resolves native completion callbacks after the worker re-admits their captured boundary. @internal */
final readonly class CSVBatchCallbacks
{
    /** Retain retryable failed chunks and remove only successfully completed temporary work.
     * @return Closure(Batch): void
     */
    public static function cleanup(CSVWorkReference $work): Closure
    {
        return static function (Batch $batch) use ($work): void {
            if ($batch->failedJobs === 0) {
                Container::getInstance()->make(CSVWorkStore::class)->delete($work);
            }
        };
    }
}
