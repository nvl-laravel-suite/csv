<?php

declare(strict_types=1);

use Nvl\Csv\Tests\TestCase;

uses(TestCase::class)->in(__DIR__.'/Feature');

/** Convert a consumer's camelCase fixture to the native snake_case service input. */
function csvOptions(array $options): array
{
    $native = [];
    foreach ($options as $key => $value) {
        $native[strtolower((string) preg_replace('/[A-Z]/', '_$0', (string) $key))] = $value;
    }

    return $native;
}
