<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Nvl\Csv\Enums\CSVEncodingEnum;
use Nvl\Csv\Exceptions\CSVFileNotFoundException;
use Nvl\Csv\Services\CsvImporter;

it('trims imported cells when trim_values is enabled and preserves them when it is disabled', function (): void {
    $path = $this->temporaryCsv("id, name\n 1 ,\" Ada \"\n  ,\t\n");

    $trimmed = CsvImporter::make()->fromFile($path);
    $preserved = CsvImporter::make()->withOptions(['trim_values' => false])->fromFile($path);

    expect(iterator_to_array($trimmed->stream()))->toBe([1 => ['id' => '1', 'name' => 'Ada']])
        ->and(iterator_to_array($preserved->stream()))->toBe([
            1 => ['id' => ' 1 ', ' name' => ' Ada '],
            2 => ['id' => '  ', ' name' => "\t"],
        ]);
});

it('detects duplicates and validates types on trimmed cells', function (): void {
    $path = $this->temporaryCsv("email\nada@example.test\n ada@example.test \n");

    $result = CsvImporter::make()->fromFile($path)->withOptions([
        'unique_fields' => ['email'],
        'duplicate_strategy' => 'skip',
        'transaction' => false,
    ])->run();

    expect($result['successful_rows'])->toBe(1)
        ->and($result['skipped_rows'])->toBe(1);
});

it('keeps a selected local or disk source when options are applied afterwards', function (): void {
    $path = $this->temporaryCsv("id,name\n1,Ada\n");
    Storage::fake('imports');
    Storage::disk('imports')->put('people.csv', "id\n7\n");

    expect(iterator_to_array(CsvImporter::make()->fromFile($path)->withOptions(['strict_mode' => true])->stream()))
        ->toBe([1 => ['id' => '1', 'name' => 'Ada']])
        ->and(iterator_to_array(CsvImporter::make()->fromDisk('imports', 'people.csv')->withOptions(['strict_mode' => true])->stream()))
        ->toBe([1 => ['id' => '7']]);
});

it('validates a file_path option like fromFile without disclosing the path', function (string $source): void {
    $path = $source === 'directory' ? sys_get_temp_dir() : sys_get_temp_dir().'/nvl-csv-missing-'.bin2hex(random_bytes(6)).'.csv';

    try {
        CsvImporter::make()->withOptions(['file_path' => $path]);
        $this->fail('Expected the file_path option to reject before parsing.');
    } catch (CSVFileNotFoundException $exception) {
        expect($exception->getMessage())->toBe('CSV source must be a readable file.')
            ->and(json_encode($exception->toArray(), JSON_THROW_ON_ERROR))->not->toContain($path);
    }
})->with(['directory', 'missing']);

it('decodes UTF-16 and UTF-32 sources without a byte order mark in the documented byte order', function (): void {
    $utf16 = $this->temporaryCsv(mb_convert_encoding("name\nAndré\n", 'UTF-16LE', 'UTF-8'));
    $utf32 = $this->temporaryCsv(mb_convert_encoding("name\nAndré\n", 'UTF-32BE', 'UTF-8'));

    expect(CSVEncodingEnum::UTF16->getPhpEncoding())->toBe('UTF-16LE')
        ->and(CSVEncodingEnum::UTF32->getPhpEncoding())->toBe('UTF-32BE')
        ->and(iterator_to_array(CsvImporter::make()->withEncoding(CSVEncodingEnum::UTF16)->fromFile($utf16)->stream()))
        ->toBe([1 => ['name' => 'André']])
        ->and(iterator_to_array(CsvImporter::make()->withEncoding(CSVEncodingEnum::UTF32)->fromFile($utf32)->stream()))
        ->toBe([1 => ['name' => 'André']]);
});
