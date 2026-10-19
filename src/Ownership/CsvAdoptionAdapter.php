<?php

declare(strict_types=1);

namespace Nvl\Csv\Ownership;

use Illuminate\Contracts\Filesystem\Factory;
use Nvl\Ownership\Contracts\OwnershipAdoptionAdapter;
use Nvl\Ownership\ValueObjects\OwnershipAdoptionPlan;
use Nvl\Ownership\ValueObjects\OwnershipBackfillResult;
use Nvl\Ownership\ValueObjects\OwnershipVerification;
use Nvl\Support\Ownership\Exceptions\OwnershipViolation;
use Throwable;

/** Declares that CSV has no durable relational resources to adopt. */
final readonly class CsvAdoptionAdapter implements OwnershipAdoptionAdapter
{
    public function __construct(private Factory $storage) {}

    public function resources(): array
    {
        return [];
    }

    public function prepare(OwnershipAdoptionPlan $plan): void
    {
        $verification = $this->verify($plan);
        if (! $verification->passed()) {
            throw new OwnershipViolation('CSV ownership readiness requires private local storage and drained legacy manifests.');
        }
    }

    public function backfill(OwnershipAdoptionPlan $plan, ?string $cursor, int $limit): OwnershipBackfillResult
    {
        return new OwnershipBackfillResult(null, 0);
    }

    /** @phpstan-impure */
    public function verify(OwnershipAdoptionPlan $plan): OwnershipVerification
    {
        $errors = [];
        try {
            $disk = $this->storage->disk('local');
            $legacy = [...$disk->allFiles('csv-processing'), ...$disk->allFiles('csv_processing_chunks')];
            foreach ($disk->allFiles('tenants') as $path) {
                if (str_contains($path, '/csv/')) {
                    $legacy[] = $path;
                }
            }
            if ($legacy !== []) {
                $errors[] = 'csv.legacy_manifests_not_drained';
            }
        } catch (Throwable) {
            $errors[] = 'csv.private_local_storage_unavailable';
        }

        return new OwnershipVerification($errors);
    }

    public function activate(OwnershipAdoptionPlan $plan): void
    {
        if (! $this->verify($plan)->passed()) {
            throw new OwnershipViolation('CSV ownership readiness did not verify.');
        }
    }
}
