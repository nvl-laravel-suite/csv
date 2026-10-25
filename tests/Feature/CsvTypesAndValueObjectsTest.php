<?php

declare(strict_types=1);

use Nvl\Csv\Enums\CSVDataQualityEnum;
use Nvl\Csv\Enums\CSVDelimiterEnum;
use Nvl\Csv\Enums\CSVDuplicateStrategyEnum;
use Nvl\Csv\Enums\CSVEncodingEnum;
use Nvl\Csv\Enums\CSVErrorLevelEnum;
use Nvl\Csv\Enums\CSVExportFormatEnum;
use Nvl\Csv\Enums\CSVNotificationChannelEnum;
use Nvl\Csv\Enums\CSVOperationStatusEnum;
use Nvl\Csv\Enums\CSVProcessingModeEnum;
use Nvl\Csv\Enums\CSVTypeEnum;
use Nvl\Csv\Exceptions\CSVConfigurationException;
use Nvl\Csv\Exceptions\CSVException;
use Nvl\Csv\Exceptions\CSVFileNotFoundException;
use Nvl\Csv\Exceptions\CSVMemoryException;
use Nvl\Csv\Exceptions\CSVParseException;
use Nvl\Csv\Exceptions\CSVValidationException;
use Nvl\Csv\Services\CsvAnalyzer;
use Nvl\Csv\Services\CsvExporter;
use Nvl\Csv\Services\CsvImporter;
use Nvl\Csv\ValueObjects\CSVConfiguration;
use Nvl\Csv\ValueObjects\CSVFieldMapping;

it('exposes every delimiter, format, encoding, and processing mode contract', function (): void {
    foreach (CSVDelimiterEnum::cases() as $delimiter) {
        expect($delimiter->getCharacter())->toBeString()
            ->and($delimiter->label())->toBeString()
            ->and($delimiter->getFileExtension())->toBeString()
            ->and(CSVDelimiterEnum::fromCharacter($delimiter->getCharacter()))->toBe($delimiter);
    }
    expect(CSVDelimiterEnum::fromCharacter('~'))->toBeNull();

    foreach (CSVExportFormatEnum::cases() as $format) {
        expect($format->getSettings())->toHaveKeys([
            'delimiter', 'enclosure', 'escape', 'line_ending', 'include_bom',
        ])->and($format->label())->toBeString()
            ->and($format->description())->toBeString()
            ->and($format->getFileExtension())->toBeString()
            ->and($format->includeBOM())->toBeBool()
            ->and($format->getDelimiter())->toBeString()
            ->and($format->getEnclosure())->toBeString()
            ->and($format->getEscape())->toBeString()
            ->and($format->getLineEnding())->toBeString();
    }

    foreach (CSVEncodingEnum::cases() as $encoding) {
        expect($encoding->getPhpEncoding())->toBeString()
            ->and($encoding->hasBOM())->toBeBool()
            ->and($encoding->getBOM())->toBeString()
            ->and($encoding->canConvert('UTF-8'))->toBeBool()
            ->and($encoding->getEncodingFamily())->toBeString()
            ->and($encoding->label())->toBeString();
    }
    expect(CSVEncodingEnum::detectFromBOM("\xEF\xBB\xBFdata"))->toBe(CSVEncodingEnum::UTF8_BOM)
        ->and(CSVEncodingEnum::detectFromBOM("\xFF\xFE\x00\x00data"))->toBe(CSVEncodingEnum::UTF32_LE)
        ->and(CSVEncodingEnum::detectFromBOM("\xFF\xFEdata"))->toBe(CSVEncodingEnum::UTF16_LE)
        ->and(CSVEncodingEnum::detectFromBOM("\xFE\xFFdata"))->toBe(CSVEncodingEnum::UTF16_BE)
        ->and(CSVEncodingEnum::detectFromBOM("\x00\x00\xFE\xFFdata"))->toBe(CSVEncodingEnum::UTF32)
        ->and(CSVEncodingEnum::detectFromBOM('plain'))->toBeNull()
        ->and(CSVEncodingEnum::excelRecommended())->toBe(CSVEncodingEnum::UTF8_BOM)
        ->and(CSVEncodingEnum::excelRecommended(false))->toBe(CSVEncodingEnum::UTF8);

    foreach (CSVProcessingModeEnum::cases() as $mode) {
        expect($mode->getDefaultChunkSize())->toBeInt()
            ->and($mode->supportsFiltering())->toBeBool()
            ->and($mode->supportsRandomAccess())->toBeBool()
            ->and($mode->getRecommendedMemoryLimit())->toBeInt()
            ->and($mode->isSuitableForLargeFiles())->toBeBool()
            ->and($mode->label())->toBeString()
            ->and($mode->description())->toBeString()
            ->and($mode->requiresQueue())->toBeBool()
            ->and($mode->getPerformanceCharacteristics())->toHaveKeys(['speed', 'memory', 'scalability']);
    }
});

it('exposes all quality, severity, duplicate, status, and notification metadata', function (): void {
    foreach (CSVDataQualityEnum::cases() as $quality) {
        expect($quality->getValidityThreshold())->toBeFloat()
            ->and($quality->getValidityRange())->toHaveCount(2)
            ->and($quality->requiresManualReview())->toBeBool()
            ->and($quality->canAutoProcess())->toBeBool()
            ->and($quality->getImportStrategy())->toBeString()
            ->and($quality->getScoreMultiplier())->toBeFloat()
            ->and($quality->getConfidenceLevel())->toBeString()
            ->and($quality->getColor())->toBeString()
            ->and($quality->getIcon())->toBeString()
            ->and($quality->label())->toBeString()
            ->and($quality->getDescription())->toBeString()
            ->and($quality->getRecommendedActions())->toBeArray();
    }
    expect(CSVDataQualityEnum::fromScore(100))->toBe(CSVDataQualityEnum::EXCELLENT)
        ->and(CSVDataQualityEnum::fromScore(85))->toBe(CSVDataQualityEnum::GOOD)
        ->and(CSVDataQualityEnum::fromScore(65))->toBe(CSVDataQualityEnum::FAIR)
        ->and(CSVDataQualityEnum::fromScore(45))->toBe(CSVDataQualityEnum::POOR)
        ->and(CSVDataQualityEnum::fromScore(5))->toBe(CSVDataQualityEnum::CRITICAL)
        ->and(CSVDataQualityEnum::fromColumnScores([]))->toBe(CSVDataQualityEnum::CRITICAL)
        ->and(CSVDataQualityEnum::fromColumnScores([100, 20]))->toBeInstanceOf(CSVDataQualityEnum::class)
        ->and(CSVDataQualityEnum::productionReady())->toHaveCount(2)
        ->and(CSVDataQualityEnum::requiresIntervention())->toHaveCount(2);

    foreach (CSVErrorLevelEnum::cases() as $level) {
        expect($level->shouldStopProcessing())->toBeBool()
            ->and($level->shouldSkipRow())->toBeBool()
            ->and($level->shouldLog())->toBeBool()
            ->and($level->shouldLog(true))->toBeBool()
            ->and($level->shouldNotify())->toBeBool()
            ->and($level->getNumericLevel())->toBeInt()
            ->and($level->isMoreSevereThan(CSVErrorLevelEnum::INFO))->toBeBool()
            ->and($level->meetsThreshold(CSVErrorLevelEnum::WARNING))->toBeBool()
            ->and($level->getLogLevel())->toBeString()
            ->and($level->getConsoleColor())->toBeString()
            ->and($level->getIcon())->toBeString()
            ->and($level->label())->toBeString()
            ->and($level->getImpactDescription())->toBeString();
    }
    expect(CSVErrorLevelEnum::fromNumeric(1))->toBe(CSVErrorLevelEnum::DEBUG)
        ->and(CSVErrorLevelEnum::fromNumeric(0))->toBeNull()
        ->and(CSVErrorLevelEnum::sortedBySeverity())->toHaveCount(5)
        ->and(CSVErrorLevelEnum::stoppingLevels())->toBe([CSVErrorLevelEnum::CRITICAL])
        ->and(CSVErrorLevelEnum::skippingLevels())->toHaveCount(2);

    foreach (CSVDuplicateStrategyEnum::cases() as $strategy) {
        expect($strategy->shouldCheckExisting())->toBeBool()
            ->and($strategy->modifiesExisting())->toBeBool()
            ->and($strategy->createsNew())->toBeBool()
            ->and($strategy->preservesExisting())->toBeBool()
            ->and($strategy->stopsProcessing())->toBeBool()
            ->and($strategy->getMergeStrategy())->toBeString()
            ->and($strategy->getSqlOperation())->toBeString()
            ->and($strategy->getPriority())->toBeInt()
            ->and($strategy->requiresUniqueIdentifier())->toBeBool()
            ->and($strategy->getPerformanceImpact())->toBeString()
            ->and($strategy->label())->toBeString()
            ->and($strategy->getDescription())->toBeString()
            ->and($strategy->getIcon())->toBeString()
            ->and($strategy->getAuditMessage())->toBeString();
    }
    expect(CSVDuplicateStrategyEnum::upsertStrategies())->not->toBeEmpty()
        ->and(CSVDuplicateStrategyEnum::historyPreserving())->not->toBeEmpty()
        ->and(CSVDuplicateStrategyEnum::productionSafe())->not->toBeEmpty();

    foreach (CSVOperationStatusEnum::cases() as $status) {
        foreach (CSVOperationStatusEnum::cases() as $target) {
            expect($status->canTransitionTo($target))->toBeBool();
        }

        expect($status->isActive())->toBeBool()
            ->and($status->isTerminal())->toBeBool()
            ->and($status->canPause())->toBeBool()
            ->and($status->canResume())->toBeBool()
            ->and($status->canCancel())->toBeBool()
            ->and($status->canRetry())->toBeBool()
            ->and($status->isSuccess())->toBeBool()
            ->and($status->isFailure())->toBeBool()
            ->and($status->getValidTransitions())->toBeArray()
            ->and($status->getProgressRange())->toHaveCount(2)
            ->and($status->getColor())->toBeString()
            ->and($status->getIcon())->toBeString()
            ->and($status->label())->toBeString()
            ->and($status->getDescription())->toBeString()
            ->and($status->getPriority())->toBeInt();
    }
    expect(CSVOperationStatusEnum::inProgress())->not->toBeEmpty()
        ->and(CSVOperationStatusEnum::terminal())->toHaveCount(3);

    foreach (CSVNotificationChannelEnum::cases() as $channel) {
        expect($channel->requiresConfiguration())->toBeBool()
            ->and($channel->supportsRichContent())->toBeBool()
            ->and($channel->supportsAttachments())->toBeBool()
            ->and($channel->isRealTime())->toBeBool()
            ->and($channel->getPriority())->toBeInt()
            ->and($channel->getReliability())->toBeString()
            ->and($channel->getDeliverySpeed())->toBeString()
            ->and($channel->getCostTier())->toBeString()
            ->and($channel->getIcon())->toBeString()
            ->and($channel->label())->toBeString()
            ->and($channel->getDescription())->toBeString()
            ->and($channel->getRequiredConfig())->toBeArray()
            ->and($channel->getSuitableNotificationTypes())->toBeArray();
    }
    expect(CSVNotificationChannelEnum::errorChannels())->not->toBeEmpty()
        ->and(CSVNotificationChannelEnum::progressChannels())->not->toBeEmpty()
        ->and(CSVNotificationChannelEnum::reportChannels())->not->toBeEmpty();
});

it('casts and validates every CSV field type safely', function (): void {
    expect(CSVTypeEnum::STRING->cast(42))->toBe('42')
        ->and(CSVTypeEnum::INTEGER->cast('42'))->toBe(42)
        ->and(CSVTypeEnum::FLOAT->cast('4.2'))->toBe(4.2)
        ->and(CSVTypeEnum::BOOLEAN->cast('yes'))->toBeTrue()
        ->and(CSVTypeEnum::BOOLEAN->cast('no'))->toBeFalse()
        ->and(CSVTypeEnum::DATE->cast('2026-01-02'))->toBe('2026-01-02')
        ->and(CSVTypeEnum::DATETIME->cast('2026-01-02 03:04:05'))->toBe('2026-01-02 03:04:05')
        ->and(CSVTypeEnum::EMAIL->cast('person@example.com'))->toBe('person@example.com')
        ->and(CSVTypeEnum::JSON->cast(['id' => 1]))->toBe('{"id":1}')
        ->and(CSVTypeEnum::ARRAY->cast('["a","b"]'))->toBe(['a', 'b'])
        ->and(CSVTypeEnum::ARRAY->cast('a,b'))->toBe(['a', 'b'])
        ->and(CSVTypeEnum::ARRAY->cast(1))->toBe([1])
        ->and(CSVTypeEnum::NULLABLE_STRING->cast(''))->toBeNull()
        ->and(CSVTypeEnum::NULLABLE_INTEGER->cast(null))->toBeNull()
        ->and(CSVTypeEnum::NULLABLE_FLOAT->cast(''))->toBeNull();

    foreach (CSVTypeEnum::cases() as $type) {
        expect($type->getPhpType())->toBeString()
            ->and($type->isNullable())->toBeBool()
            ->and($type->getDefaultValue())->toBeIn([null, '', 0, 0.0, false, '{}', []])
            ->and($type->validate($type->getDefaultValue()))->toBeBool();
    }

    expect(CSVTypeEnum::INTEGER->validate('4.2'))->toBeFalse()
        ->and(CSVTypeEnum::BOOLEAN->validate(2))->toBeFalse()
        ->and(CSVTypeEnum::BOOLEAN->validate('perhaps'))->toBeFalse()
        ->and(CSVTypeEnum::EMAIL->validate('bad'))->toBeFalse()
        ->and(CSVTypeEnum::JSON->validate(['id' => 1]))->toBeTrue()
        ->and(CSVTypeEnum::JSON->validate('{bad'))->toBeFalse()
        ->and(CSVTypeEnum::DATE->validate('2026-02-31'))->toBeFalse()
        ->and(CSVTypeEnum::DATE->validate([]))->toBeFalse();

    expect(fn () => CSVTypeEnum::INTEGER->cast('4.2'))->toThrow(InvalidArgumentException::class);
    expect(fn () => CSVTypeEnum::FLOAT->cast([]))->toThrow(InvalidArgumentException::class);
    expect(fn () => CSVTypeEnum::BOOLEAN->cast(2))->toThrow(InvalidArgumentException::class);
    expect(fn () => CSVTypeEnum::JSON->cast('{bad'))->toThrow(InvalidArgumentException::class);
    expect(fn () => CSVTypeEnum::BOOLEAN->cast('perhaps'))->toThrow(InvalidArgumentException::class);
    expect(fn () => CSVTypeEnum::EMAIL->cast('bad'))->toThrow(InvalidArgumentException::class);
    expect(fn () => CSVTypeEnum::STRING->cast([]))->toThrow(InvalidArgumentException::class);
});

it('validates native option arrays and preserves configuration invariants', function (): void {
    $configuration = CSVConfiguration::fromFormat(CSVExportFormatEnum::EXCEL)
        ->withDelimiter(';')->withProcessingMode(CSVProcessingModeEnum::STREAM)
        ->withChunkSize(250)->withIncludeIndex();
    expect($configuration->delimiter)->toBe(';')
        ->and($configuration->includeIndex)->toBeTrue()
        ->and($configuration->isStreaming())->toBeTrue()
        ->and($configuration->isChunked())->toBeTrue()
        ->and($configuration->getEffectiveChunkSize())->toBe(250)
        ->and($configuration->toArray()['processing_mode'])->toBe('stream')
        ->and(CSVConfiguration::fromDelimiter(CSVDelimiterEnum::TAB)->delimiter)->toBe("\t")
        ->and(CSVConfiguration::default()->isChunked())->toBeFalse()
        ->and(CSVConfiguration::excel()->includeBOM)->toBeTrue()
        ->and(CSVConfiguration::largeFile()->isStreaming())->toBeTrue();
    expect(fn () => (new CsvImporter)->withOptions(['unknown' => true]))->toThrow(CSVConfigurationException::class);
    expect(fn () => (new CsvExporter)->withOptions(['unknown' => true]))->toThrow(CSVConfigurationException::class);
});

it('tracks native import progress and bounded row statistics', function (): void {
    $path = $this->temporaryCsv("id\n1\n2\n");
    $importer = (new CsvImporter)->fromFile($path)->withTransaction(false);
    $result = $importer->run();
    expect($result['total_rows'])->toBe(2)
        ->and($result['successful_rows'])->toBe(2)
        ->and($result['failed_rows'])->toBe(0)
        ->and($result['processing_time'])->toBeGreaterThanOrEqual(0.0)
        ->and($importer->getProgress())->toMatchArray(['status' => 'complete', 'processed_rows' => 2]);
});

it('returns native import export and analysis records', function (): void {
    $path = $this->temporaryCsv("id,name\n1,Ada\n");
    $analysis = (new CsvAnalyzer)->analyze($path);
    $import = (new CsvImporter)->fromFile($path)->run();
    $export = (new CsvExporter)->export([['id' => 1, 'name' => 'Ada']], ['headings' => ['ID', 'Name']]);
    expect($analysis)->toHaveKeys(['row_count', 'detected_delimiter', 'column_analysis', 'issues'])
        ->and($analysis['row_count'])->toBe(1)
        ->and($import)->toHaveKeys(['successful_rows', 'failed_rows', 'errors', 'metadata'])
        ->and($import['successful_rows'])->toBe(1)
        ->and($export)->toContain("ID,Name\n1,Ada\n");
});

it('preserves field mapping factories, serialization, validation, and metadata', function (): void {
    $simple = CSVFieldMapping::simple('name', 'display_name');
    $typed = CSVFieldMapping::typed('age', 'age', CSVTypeEnum::INTEGER, true);
    $transformed = new CSVFieldMapping(
        sourceField: 'name',
        targetField: 'name',
        defaultValue: 'Unknown',
        transformer: fn (mixed $value): string => strtoupper((string) $value),
        validators: [fn (mixed $value): bool => $value !== 'forbidden'],
        unique: true,
        metadata: ['label' => 'Name'],
    );
    $restored = unserialize(serialize($simple));

    expect($simple->apply('Jane'))->toBe('Jane')
        ->and($typed->validate('42'))->toBeTrue()
        ->and($typed->apply('42'))->toBe(42)
        ->and($typed->validate(''))->toBeFalse()
        ->and($typed->getValidationErrors(''))->not->toBeEmpty()
        ->and($restored)->toBeInstanceOf(CSVFieldMapping::class)
        ->and($restored->apply('jane'))->toBe('jane')
        ->and($transformed->apply('jane'))->toBe('JANE')
        ->and($transformed->validate('forbidden'))->toBeFalse()
        ->and($transformed->apply(''))->toBe('Unknown')
        ->and($transformed->shouldIndex())->toBeTrue()
        ->and($transformed->toArray()['metadata'])->toBe(['label' => 'Name']);
    expect(fn () => serialize($transformed))->toThrow(InvalidArgumentException::class);
});

it('builds contextual exceptions for every compatibility factory', function (): void {
    $base = (new CSVConfigurationException('Failure'))->withContext(['row' => 2]);
    $emptyFile = $this->temporaryCsv('');
    expect($base->getContext())->toBe(['row' => 2])
        ->and($base->getContextValue('row'))->toBe(2)
        ->and($base->getContextValue('missing', 'fallback'))->toBe('fallback')
        ->and($base->toArray())->toMatchArray(['message' => 'Failure', 'context' => ['row' => 2]]);

    $exceptions = [
        CSVConfigurationException::invalidDelimiter('~'),
        CSVConfigurationException::invalidEnclosure('xx'),
        CSVConfigurationException::invalidChunkSize(0),
        CSVConfigurationException::invalidProcessingMode('bad'),
        CSVConfigurationException::missingConfiguration('file'),
        CSVConfigurationException::invalidDisk('disk'),
        CSVConfigurationException::invalidMemoryLimit(0),
        CSVFileNotFoundException::fileNotFound('/tmp/a.csv'),
        CSVFileNotFoundException::fileNotFoundOnDisk('s3', 'a.csv'),
        CSVFileNotFoundException::directoryNotFound('/tmp/nope'),
        CSVFileNotFoundException::fileNotReadable('/tmp/nope'),
        CSVFileNotFoundException::pathNotWritable('/tmp/nope'),
        CSVFileNotFoundException::fileEmpty($emptyFile),
        CSVMemoryException::memoryLimitExceeded(100, 50),
        CSVMemoryException::fileTooLarge('a.csv', 100, 50),
        CSVMemoryException::insufficientMemory(100, 50),
        CSVMemoryException::chunkSizeTooLarge(100, 10),
        CSVMemoryException::allocationFailed('buffer'),
        CSVParseException::invalidStructure('bad', 2),
        CSVParseException::headerMismatch(['id'], ['name']),
        CSVParseException::columnCountMismatch(2, 1, 3),
        CSVParseException::invalidEncoding('UTF-8', 2),
        CSVParseException::missingRequiredColumn('id'),
        CSVParseException::cannotDetectDelimiter(),
        CSVParseException::parsingFailed('bad', 2),
        CSVParseException::invalidHeaders(),
        CSVValidationException::fieldValidationFailed('id', [], 'integer'),
        CSVValidationException::invalidType('id', [], 'integer'),
        CSVValidationException::requiredFieldMissing('id', 2),
        CSVValidationException::duplicateValue('id', 1, 2),
        CSVValidationException::missingRequiredFields(['id']),
    ];

    foreach ($exceptions as $exception) {
        expect($exception)->toBeInstanceOf(CSVException::class)
            ->and($exception->getMessage())->not->toBeEmpty();
    }

    $validation = CSVValidationException::validationFailed([
        2 => ['email' => ['Invalid']],
        4 => ['name' => ['Required']],
    ]);
    $rowValidation = CSVValidationException::rowValidationFailed(3, ['email' => ['Invalid']]);

    expect($validation->getErrorCount())->toBe(2)
        ->and($validation->getAffectedRows())->toBe([2, 4])
        ->and($validation->getRowErrors(2))->toBe(['email' => ['Invalid']])
        ->and($validation->getRowErrors(99))->toBeNull()
        ->and($validation->getValidationErrors())->toHaveCount(2)
        ->and($rowValidation->getAffectedRows())->toBe([3]);
});
