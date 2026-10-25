<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Nvl\Csv\Enums\CSVDelimiterEnum;
use Nvl\Csv\Enums\CSVTypeEnum;
use Nvl\Csv\Services\CsvAnalyzer;
use Nvl\Csv\Services\CsvExporter;
use Nvl\Csv\Services\CsvImporter;
use Nvl\Csv\ValueObjects\CSVFieldMapping;

it('supports a consumer analyze import export workflow through public APIs', function (): void {
    Storage::fake('consumer-exports');
    $sourcePath = $this->temporaryCsv("external_id;name\n42;Jane Doe\n43;John Doe\n");

    $sourceAnalysis = (new CsvAnalyzer)->analyze($sourcePath);
    $importedRows = [];
    $importOptions = csvOptions([
        'filePath' => $sourcePath,
        'delimiter' => $sourceAnalysis['detected_delimiter'],
        'encoding' => $sourceAnalysis['detected_encoding'],
        'hasHeaders' => $sourceAnalysis['headers'] !== [],
        'chunkSize' => $sourceAnalysis['requires_chunking']
            ? $sourceAnalysis['recommended_chunk_size']
            : null,
        'skipEmptyRows' => $sourceAnalysis['empty_row_count'] > 0,
        'strictMode' => false,
    ]);

    $import = CsvImporter::make()
        ->withOptions($importOptions)
        ->mapField(
            'external_id',
            'id',
            CSVFieldMapping::typed('external_id', 'id', CSVTypeEnum::INTEGER, required: true),
        )
        ->mapField('name', 'display_name')
        ->processRow(function (array $row) use (&$importedRows): array {
            $importedRows[] = $row;

            return $row;
        })
        ->withTransaction(false)
        ->run();

    $export = CsvExporter::make()
        ->disk('consumer-exports')
        ->path('reports')
        ->filename('people.csv')
        ->headings(['ID', 'Display Name'])
        ->fields(['id', 'display_name'])
        ->fromArray($importedRows);

    $exportAnalysis = (new CsvAnalyzer)->analyzeDisk(
        'consumer-exports',
        'reports/people.csv',
    );

    expect($sourceAnalysis['detected_delimiter'])->toBe(CSVDelimiterEnum::SEMICOLON)
        ->and($import['failed_rows'] === 0)->toBeTrue()
        ->and($importedRows)->toBe([
            ['id' => 42, 'display_name' => 'Jane Doe'],
            ['id' => 43, 'display_name' => 'John Doe'],
        ])
        ->and($export['row_count'] > 0)->toBeTrue()
        ->and($export['row_count'])->toBe(2)
        ->and($exportAnalysis['headers'])->toBe(['ID', 'Display Name'])
        ->and($exportAnalysis['row_count'])->toBe(2)
        ->and(Storage::disk('consumer-exports')->get('reports/people.csv'))
        ->toContain("42,\"Jane Doe\"\n");
});
