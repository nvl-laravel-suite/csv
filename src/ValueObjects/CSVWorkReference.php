<?php

declare(strict_types=1);

namespace Nvl\Csv\ValueObjects;

use Nvl\Support\Ownership\ValueObjects\OwnershipJobEnvelope;

/** Scalar-only identity for one tenant CSV work manifest. */
final readonly class CSVWorkReference
{
    public function __construct(
        public string $workId,
        public ?string $tenantId,
        public string $disk,
        public string $manifestPath,
        public string $handlerAlias,
        public int $handlerVersion,
        public ?OwnershipJobEnvelope $ownership = null,
    ) {}

    /** Create an opaque private path bound to the complete producer boundary. */
    public static function forOwnership(string $workId, OwnershipJobEnvelope $ownership, string $disk, string $handlerAlias, int $handlerVersion): self
    {
        return new self($workId, null, $disk, self::ownershipPath($workId, $ownership), $handlerAlias, $handlerVersion, $ownership);
    }

    /** Derive a canonical path without using request-supplied owner IDs or model names. */
    public static function ownershipPath(string $workId, OwnershipJobEnvelope $ownership): string
    {
        $partition = hash('sha256', json_encode($ownership->toArray(), JSON_THROW_ON_ERROR));

        return 'ownership/'.$partition.'/csv/'.$workId.'/manifest.json';
    }
}
