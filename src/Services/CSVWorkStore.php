<?php

declare(strict_types=1);

namespace Nvl\Csv\Services;

use Nvl\Csv\Compatibility\LegacyCSVWorkStore;
use Nvl\Csv\Ownership\OwnershipCSVWorkStore;
use Nvl\Csv\ValueObjects\CSVWorkReference;

/** Reads only the manifest format explicitly declared by a captured scalar work reference. */
final readonly class CSVWorkStore
{
    public function __construct(private LegacyCSVWorkStore $legacy, private OwnershipCSVWorkStore $native) {}

    /** @param array<string,mixed> $manifest */
    public function write(CSVWorkReference $work, array $manifest): ?string
    {
        if ($work->ownership !== null) {
            return $this->native->write($work, $manifest);
        }
        $this->legacy->write($work, $manifest);

        return null;
    }

    /** @return array<string,mixed> */
    public function read(CSVWorkReference $work, ?string $expectedChecksum = null): array
    {
        return $work->ownership === null ? $this->legacy->read($work) : $this->native->read($work, $expectedChecksum);
    }

    public function delete(CSVWorkReference $work): void
    {
        ($work->ownership === null ? $this->legacy : $this->native)->delete($work);
    }
}
