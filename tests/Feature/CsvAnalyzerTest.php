<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Nvl\Csv\Enums\CSVDelimiterEnum;
use Nvl\Csv\Enums\CSVEncodingEnum;
use Nvl\Csv\Exceptions\CSVFileNotFoundException;
use Nvl\Csv\Services\CsvAnalyzer;

it('analyzes standard quoted fields ending in a backslash', function (): void {
    $path = $this->temporaryCsv("path,label\n\"folder,\\\",first\nplain,second\n");
    $result = (new CsvAnalyzer)->analyze($path);

    expect($result['row_count'])->toBe(2)
        ->and($result['column_count'])->toBe(2)
        ->and($result['inconsistent_row_count'])->toBe(0);
});

it('detects dialect, types, duplicates, quality issues, and recommendations', function (): void {
    $path = $this->temporaryCsv(
        "id;name;amount;active;created_at\r\n".
        "1;\"Doe, Jane\";10.50;yes;2026-01-01\r\n".
        "2;John;20;no;2026-01-02\r\n".
        "2;John;30;yes;2026-01-03\r\n".
        ";;;;\r\n".
        "4;Only two\r\n",
    );

    $result = (new CsvAnalyzer)->analyze($path);

    expect($result['detected_delimiter'])->toBe(CSVDelimiterEnum::SEMICOLON)
        ->and($result['detected_encoding'])->toBe(CSVEncodingEnum::UTF8)
        ->and($result['line_ending'])->toBe('CRLF')
        ->and($result['row_count'])->toBe(5)
        ->and($result['column_count'])->toBe(5)
        ->and($result['empty_row_count'])->toBe(1)
        ->and($result['inconsistent_row_count'])->toBe(1)
        ->and($result['column_analysis']['amount']['type'])->toBe('numeric')
        ->and($result['column_analysis']['active']['type'])->toBe('boolean')
        ->and($result['column_analysis']['created_at']['type'])->toBe('date')
        ->and($result['duplicate_analysis']['id'])->toBeGreaterThan(0)
        ->and($result['issues'] !== [])->toBeTrue()
        ->and($result['issues'])->not->toBeEmpty()
        ->and($result['detected_delimiter']->value)->toBe('semicolon')
        ->and($result['column_count'])->toBe(5);
});

it('resets analyzer state between full and quick analyses', function (): void {
    $first = $this->temporaryCsv("id,name\n1,A\n2,B\n");
    $second = $this->temporaryCsv("code|value\nx|10\n");
    $analyzer = new CsvAnalyzer;

    $firstResult = $analyzer->analyze($first);
    $secondResult = $analyzer->analyze($second);
    $quick = $analyzer->quickAnalyze($first);

    expect($firstResult['row_count'])->toBe(2)
        ->and($secondResult['row_count'])->toBe(1)
        ->and($secondResult['headers'])->toBe(['code', 'value'])
        ->and($secondResult['detected_delimiter'])->toBe(CSVDelimiterEnum::PIPE)
        ->and($quick['sample_rows'])->toBe(2)
        ->and($quick['headers'])->toBe(['id', 'name']);
});

it('analyzes storage streams without requiring a local disk path', function (): void {
    Storage::fake('analysis');
    Storage::disk('analysis')->put('incoming/data.csv', "id\tname\n1\tRemote\n");

    $result = (new CsvAnalyzer)->analyzeDisk('analysis', 'incoming/data.csv');

    expect($result['file_path'])->toBe('analysis://incoming/data.csv')
        ->and($result['detected_delimiter'])->toBe(CSVDelimiterEnum::TAB)
        ->and($result['row_count'])->toBe(1)
        ->and($result['validity_score'] > 0)->toBeTrue()
        ->and($result['inconsistent_row_count'] === 0)->toBeTrue();
});

it('detects and decodes variable-length Unicode BOMs', function (): void {
    $utf8Path = $this->temporaryCsv("\xEF\xBB\xBFid,name\n1,UTF8\n");
    $utf16Path = $this->temporaryCsv(
        "\xFF\xFE".mb_convert_encoding("id,name\n1,UTF16\n", 'UTF-16LE', 'UTF-8'),
    );

    $utf8 = (new CsvAnalyzer)->analyze($utf8Path);
    $utf16 = (new CsvAnalyzer)->analyze($utf16Path);

    expect($utf8['has_bom'])->toBeTrue()
        ->and($utf8['detected_encoding'])->toBe(CSVEncodingEnum::UTF8_BOM)
        ->and($utf8['headers'])->toBe(['id', 'name'])
        ->and($utf16['has_bom'])->toBeTrue()
        ->and($utf16['detected_encoding'])->toBe(CSVEncodingEnum::UTF16_LE)
        ->and($utf16['headers'])->toBe(['id', 'name'])
        ->and($utf16['sample_data'])->not->toBeEmpty();
});

it('estimates large inputs and recommends chunked processing', function (): void {
    $contents = "id,value\n";
    for ($index = 1; $index <= 10001; $index++) {
        $contents .= "{$index},value-{$index}\n";
    }

    $result = (new CsvAnalyzer)->analyze($this->temporaryCsv($contents));

    expect($result['row_count'])->toBeGreaterThanOrEqual(10000)
        ->and($result['requires_chunking'])->toBeTrue()
        ->and($result['recommended_chunk_size'])->toBeGreaterThanOrEqual(100)
        ->and($result['analysis_time'])->toBeGreaterThanOrEqual(0.0);
});

it('scores large files from analyzed rows instead of diluting sampled issues', function (): void {
    $rows = ["id,name\n", "1\n"];
    for ($index = 2; $index <= 10001; $index++) {
        $rows[] = "{$index},Name {$index}\n";
    }

    $result = (new CsvAnalyzer)->analyze($this->temporaryCsv(implode('', $rows)));

    expect($result['row_count'])->toBeGreaterThanOrEqual(10000)
        ->and($result['inconsistent_row_count'])->toBe(1)
        ->and($result['validity_score'])->toBe(99.99);
});

it('classifies binary boolean columns as boolean rather than numeric', function (): void {
    $result = (new CsvAnalyzer)->analyze(
        $this->temporaryCsv("id,active\n1,1\n2,0\n3,1\n"),
    );

    expect($result['column_analysis']['active']['type'])->toBe('boolean');
});

it('detects delimiters across quoted fields containing embedded newlines', function (): void {
    $result = (new CsvAnalyzer)->analyze(
        $this->temporaryCsv("id;description\n1;\"first line\nsecond line\"\n2;plain\n"),
    );

    expect($result['detected_delimiter'])->toBe(CSVDelimiterEnum::SEMICOLON)
        ->and($result['row_count'])->toBe(2);
});

it('reports empty files and missing sources accurately', function (): void {
    expect(fn () => (new CsvAnalyzer)->analyze('/definitely/missing.csv'))
        ->toThrow(CSVFileNotFoundException::class);

    Storage::fake('missing-analysis');
    expect(fn () => (new CsvAnalyzer)->analyzeDisk('missing-analysis', 'missing.csv'))
        ->toThrow(CSVFileNotFoundException::class);

    $result = (new CsvAnalyzer)->analyze($this->temporaryCsv(''));

    expect($result['validity_score'] > 0)->toBeFalse()
        ->and($result['issues'])->not->toBeEmpty()
        ->and($result['requires_chunking'])->toBeFalse();
});
