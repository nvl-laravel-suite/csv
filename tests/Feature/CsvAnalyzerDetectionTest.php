<?php

declare(strict_types=1);

use Nvl\Csv\Enums\CSVEncodingEnum;
use Nvl\Csv\Services\CsvAnalyzer;

it('detects UTF-8 when the detection sample ends inside a multibyte character', function (int $offset): void {
    $contents = str_repeat('x', $offset)."name,city\n";
    while (strlen($contents) < 20000) {
        $contents .= str_repeat('я', 40).",София\n";
    }

    $result = app(CsvAnalyzer::class)->analyze($this->temporaryCsv($contents));

    expect($result['detected_encoding'])->toBe(CSVEncodingEnum::UTF8)
        ->and($result['sample_data'][0][1])->toBe('София')
        ->and($result['recommendations'])->not->toContain('Non-UTF8 encoding detected (ISO-8859-1) - consider conversion');
})->with([0, 1]);

it('still detects a single-byte encoding for a large legacy sample', function (): void {
    $contents = "name,city\n";
    while (strlen($contents) < 20000) {
        $contents .= "Andr\xE9,Orl\xE9ans\n";
    }

    expect(app(CsvAnalyzer::class)->analyze($this->temporaryCsv($contents))['detected_encoding'])
        ->not->toBe(CSVEncodingEnum::UTF8);
});

it('samples the first non-empty cell when describing a column', function (): void {
    $rows = "id,joined\n1,\n";
    foreach (range(2, 12) as $id) {
        $rows .= "{$id},15/01/2024\n";
    }

    $result = app(CsvAnalyzer::class)->analyze($this->temporaryCsv($rows));

    expect($result['column_analysis']['joined'])->toMatchArray(['type' => 'date', 'nullable' => true, 'sample' => '15/01/2024'])
        ->and($result['date_format_analysis']['joined'])->toBe(['format' => 'd/m/Y', 'sample' => '15/01/2024']);
});
