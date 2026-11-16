<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Nvl\Csv\Enums\CSVEncodingEnum;
use Nvl\Csv\Exceptions\CSVConfigurationException;
use Nvl\Csv\Exceptions\CSVFileNotFoundException;
use Nvl\Csv\Exceptions\CSVParseException;
use Nvl\Csv\Services\CsvImporter;
use Nvl\Csv\Support\CSVRecordReader;
use Nvl\Csv\ValueObjects\CSVConfiguration;

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

it('does not read beyond a requested slice', function (): void {
    $path = $this->temporaryCsv("id,name\n1,Ada\n2,\xC3\x28\n");

    expect(iterator_to_array(CsvImporter::make()->fromFile($path)->withOptions(['limit_rows' => 1])->stream()))
        ->toBe([1 => ['id' => '1', 'name' => 'Ada']])
        ->and(CsvImporter::make()->fromFile($path)->withOptions(['limit_rows' => 1])->run()['successful_rows'])->toBe(1)
        ->and(CsvImporter::make()->fromFile($path)->withOptions(['limit_rows' => 1])->batch(5, static function (): void {})['successful_rows'])->toBe(1);
});

it('honors configured Windows-1251 input encoding', function (): void {
    $path = $this->temporaryCsv(mb_convert_encoding("име,град\nИван,София\n", 'Windows-1251', 'UTF-8'));
    $configuration = new CSVConfiguration(encoding: 'Windows-1251');
    $expected = [1 => ['име' => 'Иван', 'град' => 'София']];

    expect(iterator_to_array((new CsvImporter($configuration))->fromFile($path)->stream()))->toBe($expected)
        ->and(iterator_to_array(CsvImporter::make()->configure($configuration)->fromFile($path)->stream()))->toBe($expected)
        ->and(CsvImporter::make()->configure($configuration)->getEncoding())->toBe(CSVEncodingEnum::WINDOWS_1251)
        ->and(CsvImporter::make()->withEncoding(CSVEncodingEnum::WINDOWS_1251)->configure(CSVConfiguration::default())->getEncoding())
        ->toBe(CSVEncodingEnum::UTF8)
        ->and(fn () => new CsvImporter(new CSVConfiguration(encoding: 'not-an-encoding')))
        ->toThrow(CSVConfigurationException::class, 'encoding is invalid');
});

it('rejects oversized logical records before processing', function (): void {
    $path = $this->temporaryCsv("id,payload\n1,\"".str_repeat('a', CSVRecordReader::MaxRecordBytes + 1)."\"\n");
    $processed = 0;

    expect(fn () => CsvImporter::make()->fromFile($path)->processRow(function (array $row) use (&$processed): array {
        $processed++;

        return $row;
    })->run())->toThrow(CSVParseException::class, 'logical record exceeds 8388608 bytes')
        ->and($processed)->toBe(0);
});
