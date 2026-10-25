<?php

declare(strict_types=1);

use Nvl\Csv\Contracts\CSVRowHandler;
use Nvl\Csv\Services\CsvAnalyzer;
use Nvl\Csv\Services\CsvExporter;
use Nvl\Csv\Services\CsvImporter;

it('offers native array results and registered synchronous row handling', function (): void {
    $path = $this->temporaryCsv("id,name\n1,Ada\n");
    $analysis = app(CsvAnalyzer::class)->analyze($path);
    $rows = [];
    $handler = new class($rows) implements CSVRowHandler
    {
        public function __construct(private array &$rows) {}

        public function process(array $row, int $rowNumber): void
        {
            $this->rows[$rowNumber] = $row;
        }
    };
    $result = app(CsvImporter::class)->import($path, [], $handler);
    $output = app(CsvExporter::class)->export($rows, ['headings' => ['id', 'name']]);

    expect($analysis['row_count'])->toBe(1)
        ->and($result['successful_rows'])->toBe(1)
        ->and($rows[1])->toBe(['id' => '1', 'name' => 'Ada'])
        ->and($output)->toContain('Ada');
});
