<?php

declare(strict_types=1);

namespace Nvl\Csv\Exceptions;

use Nvl\Csv\ValueObjects\CSVWorkReference;
use Throwable;

/**
 * Queued CSV dispatch failed after delivery began; the sealed staged work was retained.
 *
 * A handler may already have applied row effects (for example on the sync queue), so the work is
 * kept for reconciliation and recovery under its captured boundary instead of being deleted.
 *
 * @api
 */
final class CSVDispatchException extends CSVException
{
    private function __construct(string $message, public readonly CSVWorkReference $work, Throwable $previous)
    {
        parent::__construct($message, 0, $previous);
    }

    /** Wrap a dispatch failure with the retained work reference. */
    public static function afterDeliveryBegan(CSVWorkReference $work, Throwable $previous): self
    {
        return (new self('Queued CSV dispatch failed after delivery began; the staged work was retained.', $work, $previous))
            ->withContext(['work_id' => $work->workId, 'handler_alias' => $work->handlerAlias, 'handler_version' => $work->handlerVersion]);
    }
}
