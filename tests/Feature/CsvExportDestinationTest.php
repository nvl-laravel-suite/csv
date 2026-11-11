<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Nvl\Csv\Services\CsvExporter;

it('keeps a fluently selected destination when options are applied afterwards', function (): void {
    Storage::fake('local');
    Storage::fake('private-exports');

    $result = CsvExporter::make()->disk('private-exports')->path('reports')->filename('people.csv')
        ->withOptions(['headings' => ['ID'], 'fields' => ['id'], 'disk' => null])
        ->fromArray([['id' => 1]]);

    expect($result['disk'])->toBe('private-exports')
        ->and($result['storage_path'])->toBe('reports/people.csv')
        ->and(Storage::disk('private-exports')->get('reports/people.csv'))->toBe("ID\n1\n")
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

it('does not carry formula policy, fields or headings from one native export into the next', function (): void {
    $exporter = CsvExporter::make();
    $exporter->export([['name' => '=SUM(A1)']], ['formula_policy' => 'allow', 'fields' => ['name'], 'headings' => ['Trusted']]);

    expect($exporter->export([['id' => 1, 'name' => '=SUM(A1)']], []))->toBe("id,name\n1,'=SUM(A1)\n");
});
