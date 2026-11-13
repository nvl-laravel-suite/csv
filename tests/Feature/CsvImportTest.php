<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Nvl\Csv\Enums\CSVDuplicateStrategyEnum;
use Nvl\Csv\Enums\CSVEncodingEnum;
use Nvl\Csv\Enums\CSVErrorLevelEnum;
use Nvl\Csv\Enums\CSVTypeEnum;
use Nvl\Csv\Exceptions\CSVConfigurationException;
use Nvl\Csv\Exceptions\CSVFileNotFoundException;
use Nvl\Csv\Exceptions\CSVParseException;
use Nvl\Csv\Exceptions\CSVValidationException;
use Nvl\Csv\Services\CsvImporter;
use Nvl\Csv\ValueObjects\CSVConfiguration;
use Nvl\Csv\ValueObjects\CSVFieldMapping;

it('rejects nonfiles and unreadable sources without disclosing their paths', function (string $source): void {
    $path = $source === 'directory' ? sys_get_temp_dir() : $this->temporaryCsv("name\nAda\n");
    if ($source === 'unreadable') {
        chmod($path, 0000);
        clearstatcache(true, $path);
        if (is_readable($path)) {
            chmod($path, 0600);
            $this->markTestSkipped('The current OS principal can still read a mode0000 file.');
        }
    }

    try {
        try {
            CsvImporter::make()->fromFile($path);
            $this->fail('Expected the source to reject before parsing.');
        } catch (CSVFileNotFoundException $exception) {
            expect($exception->responseCode())->toBe('file_not_found')
                ->and($exception->getMessage())->toBe('CSV source must be a readable file.')
                ->and($exception->getContext())->toBe([])
                ->and(json_encode($exception->toArray(), JSON_THROW_ON_ERROR))->not->toContain($path);
        }
    } finally {
        if ($source === 'unreadable') {
            chmod($path, 0600);
        }
    }
})->with(['directory', 'unreadable']);

it('retains ordinary readable files and local file URIs as import sources', function (bool $uri): void {
    $path = $this->temporaryCsv("name\nAda\n");
    $source = $uri ? 'file://'.$path : $path;

    expect(array_values(iterator_to_array(CsvImporter::make()->fromFile($source)->stream())))
        ->toBe([['name' => 'Ada']]);
})->with([false, true]);

it('preserves trailing backslashes in standard quoted CSV fields', function (): void {
    $path = $this->temporaryCsv("path,label\n\"folder,\\\",first\nplain,second\n");
    $import = CsvImporter::make()
        ->withOptions(csvOptions(array_merge([], ['filePath' => $path])))
        ->fromFile($path);

    expect(array_values(iterator_to_array($import->stream())))->toBe([
        ['path' => 'folder,\\', 'label' => 'first'],
        ['path' => 'plain', 'label' => 'second'],
    ]);
});

it('rejects malformed UTF-8 in headers and data rows', function (): void {
    $header = $this->temporaryCsv("na\xFFme\nAda\n");
    $row = $this->temporaryCsv("name\nAd\xFFa\n");

    expect(fn (): array => iterator_to_array(CsvImporter::make()->fromFile($header)->stream()))
        ->toThrow(CSVParseException::class, 'Invalid character encoding')
        ->and(fn (): array => iterator_to_array(CsvImporter::make()->fromFile($row)->stream()))
        ->toThrow(CSVParseException::class, 'Invalid character encoding');
});

it('honors explicit legacy escape configuration for imports', function (): void {
    $path = $this->temporaryCsv('value,label'."\n".'"before\\"after",kept'."\n");
    $rows = CsvImporter::make()
        ->configure(new CSVConfiguration(escape: '\\'))
        ->fromFile($path)
        ->stream();

    expect(array_values(iterator_to_array($rows)))->toBe([
        ['value' => 'before\\"after', 'label' => 'kept'],
    ]);
});

it('imports mapped and transformed rows through the fluent compatibility API', function (): void {
    $path = $this->temporaryCsv(
        "name,age,email\n\"Doe, Jane\",42,jane@example.com\nJohn,31,john@example.com\n",
    );
    $processed = [];

    $result = CsvImporter::make()
        ->fromFile($path)
        ->mapField(
            'name',
            'full_name',
            CSVFieldMapping::withTransformer('name', 'full_name', fn (mixed $value): string => strtoupper((string) $value)),
        )
        ->mapField('age', 'age', CSVFieldMapping::typed('age', 'age', CSVTypeEnum::INTEGER, true))
        ->mapField('email', 'email')
        ->processRow(function (array $row, int $rowNumber) use (&$processed): array {
            $processed[$rowNumber] = $row;

            return [...$row, 'row_number' => $rowNumber];
        })
        ->run();

    expect($result['failed_rows'] === 0)->toBeTrue()
        ->and($result['successful_rows'])->toBe(2)
        ->and($result['failed_rows'])->toBe(0)
        ->and($processed[1]['full_name'])->toBe('DOE, JANE')
        ->and($processed[1]['age'])->toBe(42)
        ->and($result['metadata']['file_path'])->toBe($path);
});

it('supports streaming, row limits, skipped rows, and reusable importer state', function (): void {
    $path = $this->temporaryCsv("id,name\n1,First\n,\n2,Second\n3,Third\n");
    $options = csvOptions([
        'filePath' => $path,
        'skipRows' => 1,
        'limitRows' => 2,
        'skipEmptyRows' => true,
    ]);
    $import = CsvImporter::make()->withOptions($options)->fromFile($path);

    $firstPass = iterator_to_array($import->stream());
    $secondPass = iterator_to_array($import->stream());

    expect(array_values($firstPass))->toBe([
        ['id' => '2', 'name' => 'Second'],
        ['id' => '3', 'name' => 'Third'],
    ])->and($secondPass)->toBe($firstPass)
        ->and($import->getProgress())->not->toBeNull();
});

it('supports headerless CSV files and normalizes uneven rows', function (): void {
    $path = $this->temporaryCsv("A,B\nC\nD,E,F\n");
    $rows = iterator_to_array(
        CsvImporter::make()
            ->configure(new CSVConfiguration(includeHeaders: false))
            ->fromFile($path)
            ->stream(),
    );

    expect(array_values($rows))->toBe([
        ['col_0' => 'A', 'col_1' => 'B'],
        ['col_0' => 'C', 'col_1' => ''],
        ['col_0' => 'D', 'col_1' => 'E'],
    ]);
});

it('preserves the first headerless row on non-seekable disk streams', function (): void {
    $contents = "\xFF\xFE".mb_convert_encoding("Иван,София\nМария,Пловдив\n", 'UTF-16LE', 'UTF-8');
    $disk = Mockery::mock(FilesystemAdapter::class);
    $disk->shouldReceive('exists')->with('people.csv')->andReturnTrue();
    $disk->shouldReceive('readStream')->with('people.csv')->andReturnUsing(function () use ($contents) {
        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        if ($pair === false) {
            throw new RuntimeException('Unable to create a non-seekable CSV fixture.');
        }

        fwrite($pair[0], $contents);
        fclose($pair[0]);

        return $pair[1];
    });
    Storage::shouldReceive('disk')->with('csv-non-seekable')->andReturn($disk);

    $import = CsvImporter::make()
        ->configure(new CSVConfiguration(includeHeaders: false))
        ->fromDisk('csv-non-seekable', 'people.csv');
    $expected = [
        1 => ['col_0' => 'Иван', 'col_1' => 'София'],
        2 => ['col_0' => 'Мария', 'col_1' => 'Пловдив'],
    ];

    expect(iterator_to_array($import->stream()))->toBe($expected)
        ->and(iterator_to_array($import->stream()))->toBe($expected);
});

it('rejects uneven rows in strict mode while preserving lenient normalization', function (): void {
    $path = $this->temporaryCsv("id,name\n1,A\n2\n");

    $strictResult = CsvImporter::make()
        ->configure(new CSVConfiguration(strictMode: true))
        ->fromFile($path)
        ->withTransaction(false)
        ->run();

    expect($strictResult['successful_rows'])->toBe(1)
        ->and($strictResult['failed_rows'])->toBe(1)
        ->and($strictResult['errors'][0])->toContain('Column count mismatch');
});

it('applies DTO column mappings, column types, and metadata', function (): void {
    $path = $this->temporaryCsv("external_id,email\n42,person@example.com\n");
    $processed = [];
    $options = csvOptions([
        'filePath' => $path,
        'columnMapping' => ['external_id' => 'id'],
        'columnTypes' => ['external_id' => CSVTypeEnum::INTEGER],
        'metadata' => ['source' => 'partner-feed'],
    ]);

    $result = CsvImporter::make()
        ->withOptions($options)
        ->processRow(function (array $row) use (&$processed): array {
            $processed[] = $row;

            return $row;
        })
        ->withTransaction(false)
        ->run();

    expect($processed)->toBe([['id' => 42]])
        ->and($result['metadata'])->toMatchArray([
            'source' => 'partner-feed',
            'file_path' => $path,
        ]);
});

it('rejects non-string DTO column mapping keys', function (): void {
    $options = csvOptions([
        'filePath' => '/tmp/source.csv',
        'columnMapping' => [0 => 'id'],
    ]);

    expect(fn () => CsvImporter::make()->withOptions($options))
        ->toThrow(CSVConfigurationException::class, 'Column mapping keys must be non-empty strings.');
});

it('does not roll back a caller transaction when setup fails before its own transaction begins', function (): void {
    $path = $this->temporaryCsv('');
    DB::beginTransaction();

    try {
        expect(fn () => CsvImporter::make()->fromFile($path)->run())
            ->toThrow(CSVParseException::class);
        expect(DB::transactionLevel())->toBe(1);
    } finally {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
    }
});

it('rolls back import writes on the explicitly selected database connection', function (): void {
    config()->set('database.connections.csv_import', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]);
    $connection = DB::connection('csv_import');
    $connection->getSchemaBuilder()->create('imported_rows', function (Blueprint $table): void {
        $table->unsignedInteger('external_id');
    });
    $path = $this->temporaryCsv("id\n1\n2\n");

    $result = CsvImporter::make()
        ->fromFile($path)
        ->onConnection('csv_import')
        ->processRow(function (array $row) use ($connection): void {
            $connection->table('imported_rows')->insert([
                'external_id' => (int) $row['id'],
            ]);

            if ($row['id'] === '2') {
                throw new Exception('reject the complete import');
            }
        })
        ->run();

    expect($result['failed_rows'] === 0)->toBeFalse()
        ->and($connection->table('imported_rows')->count())->toBe(0);
});

it('rejects blank transaction connection names', function (): void {
    expect(fn (): CsvImporter => CsvImporter::make()->onConnection('  '))
        ->toThrow(InvalidArgumentException::class, 'non-empty string or null');
});

it('honors the configured error threshold when stop on error is enabled', function (): void {
    $path = $this->temporaryCsv("id\n1\n2\n3\n");

    $result = CsvImporter::make()
        ->fromFile($path)
        ->processRow(function (array $row): array {
            if ($row['id'] === '1') {
                throw new Exception('recoverable callback failure');
            }

            return $row;
        })
        ->stopOnError()
        ->withErrorThreshold(CSVErrorLevelEnum::ERROR)
        ->withTransaction(false)
        ->run();

    expect($result['failed_rows'])->toBe(1)
        ->and($result['successful_rows'])->toBe(2);
});

it('processes synchronous batches transactionally and validates batch sizes', function (): void {
    $path = $this->temporaryCsv("id,name\n1,A\n2,B\n3,C\n");
    $batches = [];

    $result = CsvImporter::make()
        ->fromFile($path)
        ->batch(2, function (array $rows, int $batchNumber) use (&$batches): void {
            $batches[$batchNumber] = $rows;
        });

    expect($result['failed_rows'] === 0)->toBeTrue()
        ->and($result['successful_rows'])->toBe(3)
        ->and($batches)->toHaveCount(2)
        ->and($batches[1])->toHaveCount(2)
        ->and($batches[2])->toHaveCount(1);

    expect(fn () => CsvImporter::make()->fromFile($path)->batch(0, static function (): void {}))
        ->toThrow(InvalidArgumentException::class, 'at least 1');
});

it('applies error thresholds to batch callback failures', function (): void {
    $path = $this->temporaryCsv("id\n1\n2\n3\n");
    $attemptedBatches = [];

    $result = CsvImporter::make()
        ->fromFile($path)
        ->stopOnError()
        ->withErrorThreshold(CSVErrorLevelEnum::CRITICAL)
        ->batch(1, function (array $rows, int $batchNumber) use (&$attemptedBatches): void {
            $attemptedBatches[] = $batchNumber;
            if ($batchNumber === 1) {
                throw new Exception('recoverable batch failure');
            }
        });

    expect($attemptedBatches)->toBe([1, 2, 3])
        ->and($result['successful_rows'])->toBe(2)
        ->and($result['failed_rows'])->toBe(1);
});

it('applies skip, error, and pass-through duplicate strategies', function (): void {
    $path = $this->temporaryCsv("email,name\na@example.com,A\na@example.com,B\n");

    $skipped = CsvImporter::make()
        ->fromFile($path)
        ->detectDuplicates('email')
        ->withDuplicateStrategy(CSVDuplicateStrategyEnum::SKIP)
        ->run();

    $errored = CsvImporter::make()
        ->fromFile($path)
        ->detectDuplicates('email')
        ->withDuplicateStrategy(CSVDuplicateStrategyEnum::ERROR)
        ->run();

    foreach ([
        CSVDuplicateStrategyEnum::CREATE,
        CSVDuplicateStrategyEnum::UPDATE,
        CSVDuplicateStrategyEnum::REPLACE,
        CSVDuplicateStrategyEnum::MERGE,
        CSVDuplicateStrategyEnum::INCREMENT,
        CSVDuplicateStrategyEnum::ARCHIVE,
    ] as $strategy) {
        $result = CsvImporter::make()
            ->fromFile($path)
            ->detectDuplicates('email')
            ->withDuplicateStrategy($strategy)
            ->run();

        expect($result['successful_rows'])->toBe(2);
    }

    expect($skipped['successful_rows'])->toBe(1)
        ->and($skipped['skipped_rows'])->toBe(1)
        ->and($errored['successful_rows'])->toBe(1)
        ->and($errored['failed_rows'])->toBe(1)
        ->and($errored['errors'][0])->toContain('Duplicate value');
});

it('does not reserve unique values from rows rejected by another unique field', function (): void {
    $path = $this->temporaryCsv("id,email\n1,first@example.com\n2,first@example.com\n2,second@example.com\n");
    $rows = [];

    $result = CsvImporter::make()
        ->fromFile($path)
        ->detectDuplicates('id')
        ->detectDuplicates('email')
        ->withDuplicateStrategy(CSVDuplicateStrategyEnum::SKIP)
        ->processRow(function (array $row) use (&$rows): void {
            $rows[] = $row;
        })
        ->run();

    expect($result['successful_rows'])->toBe(2)
        ->and($result['skipped_rows'])->toBe(1)
        ->and($rows)->toBe([
            ['id' => '1', 'email' => 'first@example.com'],
            ['id' => '2', 'email' => 'second@example.com'],
        ]);
});

it('preserves strict duplicate semantics for transformed scalar and compound values', function (): void {
    $firstObject = new stdClass;
    $secondObject = new stdClass;
    $values = [
        '0', 0, 0.0, -0.0, false, null, '', true, 1, '1', 1.0,
        '0', false, null, $firstObject, $firstObject, $secondObject,
        ['key' => 1], ['key' => '1'], ['key' => 1],
        ['a' => 1, 'b' => 2], ['b' => 2, 'a' => 1],
        NAN, NAN, INF, INF, -INF, -INF,
        [$firstObject], [$secondObject], [$firstObject],
    ];
    $path = $this->temporaryCsv("value\n".implode("\n", range(1, count($values)))."\n");
    $acceptedRows = [];

    $result = CsvImporter::make()
        ->fromFile($path)
        ->mapField('value', 'value', CSVFieldMapping::withTransformer(
            'value',
            'value',
            static fn (mixed $value): mixed => $values[(int) $value - 1],
        ))
        ->detectDuplicates('value')
        ->processRow(function (array $row, int $rowNumber) use (&$acceptedRows): void {
            $acceptedRows[] = $rowNumber;
        })
        ->run();

    expect($acceptedRows)->toBe([1, 2, 3, 5, 6, 7, 8, 9, 10, 11, 15, 17, 18, 19, 21, 22, 23, 24, 25, 27, 29, 30])
        ->and($result['successful_rows'])->toBe(22)
        ->and($result['skipped_rows'])->toBe(9);
});

it('tracks duplicates across a large unique CSV field', function (): void {
    $path = $this->temporaryCsv("id\n".implode("\n", range(1, 20000))."\n1\n10000\n20000\n");

    $result = CsvImporter::make()
        ->fromFile($path)
        ->detectDuplicates('id')
        ->run();

    expect($result['successful_rows'])->toBe(20000)
        ->and($result['skipped_rows'])->toBe(3);
});

it('uses unique field mappings and reports validation failures', function (): void {
    $path = $this->temporaryCsv("id,email\n1,invalid\n1,second@example.com\n");
    $mapping = new CSVFieldMapping(
        sourceField: 'id',
        targetField: 'identifier',
        type: CSVTypeEnum::INTEGER,
        required: true,
        unique: true,
        nullable: false,
    );

    $result = CsvImporter::make()
        ->fromFile($path)
        ->mapFields([
            'id' => $mapping,
            'email' => CSVFieldMapping::typed('email', 'email', CSVTypeEnum::EMAIL),
        ])
        ->withDuplicateStrategy(CSVDuplicateStrategyEnum::SKIP)
        ->run();

    expect($result['successful_rows'])->toBe(1)
        ->and($result['failed_rows'])->toBe(1)
        ->and($result['errors'] !== [])->toBeTrue()
        ->and($result['failed_rows_data'])->toHaveCount(1);
});

it('reads non-local storage streams and UTF-16 input with a BOM', function (): void {
    Storage::fake('csv-imports');
    Storage::disk('csv-imports')->put('incoming/remote.csv', "id,name\n1,Remote\n");

    $remoteRows = iterator_to_array(
        CsvImporter::make()->fromDisk('csv-imports', 'incoming/remote.csv')->stream(),
    );

    $utf16 = "\xFF\xFE".mb_convert_encoding("name,city\nИван,София\n", 'UTF-16LE', 'UTF-8');
    $utf16Path = $this->temporaryCsv($utf16);
    $utf16Rows = iterator_to_array(
        CsvImporter::make()
            ->fromFile($utf16Path)
            ->withEncoding(CSVEncodingEnum::UTF16_LE)
            ->stream(),
    );

    expect(array_values($remoteRows))->toBe([['id' => '1', 'name' => 'Remote']])
        ->and(array_values($utf16Rows))->toBe([['name' => 'Иван', 'city' => 'София']]);
});

it('invokes error and progress callbacks without leaking state', function (): void {
    $rows = ["value\n"];
    for ($index = 0; $index < 100; $index++) {
        $rows[] = $index === 0 ? "invalid\n" : "{$index}\n";
    }

    $path = $this->temporaryCsv(implode('', $rows));
    $reportedErrors = [];
    $progress = [];

    $result = CsvImporter::make()
        ->fromFile($path)
        ->mapField('value', 'value', CSVFieldMapping::typed('value', 'value', CSVTypeEnum::INTEGER, true))
        ->onError(function (array $row, Throwable $error, int $rowNumber) use (&$reportedErrors): void {
            $reportedErrors[] = [$row, $error->getMessage(), $rowNumber];
        })
        ->onProgress(function (array $snapshot) use (&$progress): void {
            $progress[] = $snapshot;
        })
        ->run();

    expect($result['failed_rows'])->toBe(1)
        ->and($result['successful_rows'])->toBe(99)
        ->and($reportedErrors)->toHaveCount(1)
        ->and($progress)->toHaveCount(1);
});

it('bounds retained failure diagnostics without losing failure counts', function (): void {
    $rows = ["value\n"];
    for ($index = 0; $index < 1002; $index++) {
        $rows[] = "invalid-{$index}\n";
    }

    $result = CsvImporter::make()
        ->fromFile($this->temporaryCsv(implode('', $rows)))
        ->mapField('value', 'value', CSVFieldMapping::typed('value', 'value', CSVTypeEnum::INTEGER, true))
        ->withTransaction(false)
        ->run();

    expect($result['failed_rows'])->toBe(1002)
        ->and($result['failed_rows_data'])->toHaveCount(1000)
        ->and($result['errors'])->toHaveCount(1000)
        ->and($result['warnings'])->toContain(
            'Additional CSV failure details were omitted after the retention limit was reached.',
        );
});

it('rejects missing files, empty inputs, and missing required headers', function (): void {
    expect(fn () => CsvImporter::make()->fromFile('/definitely/missing.csv'))
        ->toThrow(CSVFileNotFoundException::class);

    $empty = $this->temporaryCsv('');
    expect(fn () => CsvImporter::make()->fromFile($empty)->run())
        ->toThrow(CSVParseException::class);

    $duplicateHeaders = $this->temporaryCsv("id,id\n1,2\n");
    expect(fn () => CsvImporter::make()->fromFile($duplicateHeaders)->run())
        ->toThrow(CSVParseException::class);

    $path = $this->temporaryCsv("name\nJane\n");
    expect(fn () => CsvImporter::make()
        ->fromFile($path)
        ->mapField('email', 'email', CSVFieldMapping::typed('email', 'email', CSVTypeEnum::EMAIL, true))
        ->run())
        ->toThrow(CSVValidationException::class);
});

it('isolates per-row database constraint errors with savepoints and stops when stopOnError is enabled', function (): void {
    Schema::dropIfExists('csv_unique_rows');
    Schema::create('csv_unique_rows', function (Blueprint $table): void {
        $table->id();
        $table->string('email')->unique();
    });

    try {
        $path = $this->temporaryCsv("email\nada@example.test\nada@example.test\nbob@example.test\n");

        $result = CsvImporter::make()
            ->fromFile($path)
            ->processRow(function (array $row): array {
                DB::table('csv_unique_rows')->insert(['email' => $row['email']]);

                return $row;
            })
            ->run();

        expect($result['successful_rows'])->toBe(2)
            ->and($result['failed_rows'])->toBe(1)
            ->and(DB::table('csv_unique_rows')->count())->toBe(0);

        expect(fn () => CsvImporter::make()
            ->fromFile($path)
            ->stopOnError(true)
            ->processRow(function (array $row): array {
                DB::table('csv_unique_rows')->insert(['email' => $row['email']]);

                return $row;
            })
            ->run())->toThrow(QueryException::class);
    } finally {
        Schema::dropIfExists('csv_unique_rows');
    }
});
