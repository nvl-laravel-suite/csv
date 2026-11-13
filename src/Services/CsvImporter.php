<?php

declare(strict_types=1);

namespace Nvl\Csv\Services;

use Carbon\Carbon;
use Closure;
use DivisionByZeroError;
use Error;
use Exception;
use Generator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Nvl\Csv\Contracts\CSVRowHandler;
use Nvl\Csv\Enums\CSVDelimiterEnum;
use Nvl\Csv\Enums\CSVDuplicateStrategyEnum;
use Nvl\Csv\Enums\CSVEncodingEnum;
use Nvl\Csv\Enums\CSVErrorLevelEnum;
use Nvl\Csv\Enums\CSVProcessingModeEnum;
use Nvl\Csv\Enums\CSVTypeEnum;
use Nvl\Csv\Exceptions\CSVConfigurationException;
use Nvl\Csv\Exceptions\CSVFileNotFoundException;
use Nvl\Csv\Exceptions\CSVMemoryException;
use Nvl\Csv\Exceptions\CSVParseException;
use Nvl\Csv\Exceptions\CSVValidationException;
use Nvl\Csv\Support\CSVMemoryManager;
use Nvl\Csv\Support\CSVTransactionRunner;
use Nvl\Csv\ValueObjects\CSVConfiguration;
use Nvl\Csv\ValueObjects\CSVFieldMapping;
use RuntimeException;
use Throwable;

/**
 * Enhanced CSV import service with fluent API and advanced features.
 *
 * Features:
 * - Fluent API for configuration
 * - Field mapping and transformation
 * - Type casting and validation
 * - Batch processing with transactions
 * - Memory-efficient streaming
 * - Progress tracking
 * - Error recovery
 * - Duplicate detection
 *
 * @api
 */
final class CsvImporter
{
    private const MAX_RETAINED_FAILURES = 1000;

    private CSVConfiguration $configuration;

    /** @var array<string,mixed> */
    private array $options = [];

    private ?CSVRowHandler $activeHandler = null;

    private CSVMemoryManager $memoryManager;

    private CSVTransactionRunner $transactions;

    /** @var resource|null */
    private $handle = null;

    /** @var array<string, CSVFieldMapping> */
    private array $fieldMappings = [];

    /** @var array<int, string> */
    private array $headers = [];

    /** @var array<int, string|null>|null */
    private ?array $pendingRow = null;

    private ?Closure $rowProcessor = null;

    private ?Closure $progressCallback = null;

    private ?Closure $errorHandler = null;

    private ?string $sourceDisk = null;

    private ?string $sourcePath = null;

    private int $currentRow = 0;

    private int $successfulRows = 0;

    private int $failedRows = 0;

    private int $skippedRows = 0;

    /** @var array<int, array<string, mixed>> */
    private array $failedRowsData = [];

    /** @var array<array{level: CSVErrorLevelEnum, message: string, row?: int|null}> */
    private array $errors = [];

    /** @var array<array{level: CSVErrorLevelEnum, message: string, row?: int|null}> */
    private array $warnings = [];

    /** @var array<string,mixed>|null */
    private ?array $progress = null;

    private CSVDuplicateStrategyEnum $duplicateStrategy = CSVDuplicateStrategyEnum::SKIP;

    private CSVEncodingEnum $encoding = CSVEncodingEnum::UTF8;

    private float $startTime;

    private bool $stopOnError = false;

    private CSVErrorLevelEnum $errorThreshold = CSVErrorLevelEnum::ERROR;

    private bool $useTransaction = true;

    private ?string $transactionConnection = null;

    private bool $diagnosticsTruncated = false;

    /** @var array<string, array<string, list<mixed>>> */
    private array $uniqueIndexes = [];

    /**
     * Create a new CSV import service instance.
     *
     * Initializes the import service with configuration, memory management,
     * and progress tracking. If no configuration is provided, uses default settings.
     *
     * @param  CSVConfiguration|null  $configuration  Import configuration settings (null = use defaults)
     * @return void
     */
    public function __construct(?CSVConfiguration $configuration = null)
    {
        $this->configuration = $configuration ?? CSVConfiguration::default();
        $this->memoryManager = new CSVMemoryManager;
        $this->transactions = new CSVTransactionRunner;
        $this->startTime = microtime(true);
    }

    /**
     * Import with a host handler inside the selected connection transaction.
     *
     * @param  array<string,mixed>  $options  Parsing, mapping, duplicate and transaction options.
     * @return array<string,mixed>
     */
    public function import(string $path, array $options, CSVRowHandler $handler): array
    {
        $this->withOptions($options);
        $this->fromFile($path);
        $this->activeHandler = $handler;
        try {
            return $this->runImport();
        } finally {
            $this->activeHandler = null;
        }
    }

    /**
     * Execute a configured synchronous import, including its local row callback.
     *
     * @internal
     *
     * @return array<string,mixed>
     */
    public function run(): array
    {
        return $this->runImport();
    }

    /**
     * Create a new import instance with fluent API.
     *
     * Static factory method for creating CSV import instances using
     * a fluent interface pattern for easy method chaining.
     *
     * @return self New CSV import instance with default configuration
     */
    public static function make(): self
    {
        return new self;
    }

    /**
     * Set the CSV import configuration.
     *
     * Replaces the current configuration with the provided one.
     * Used to customize parsing behavior, memory limits, and processing options.
     *
     * @param  CSVConfiguration  $configuration  New configuration settings
     * @return self Returns this instance for method chaining
     */
    public function configure(CSVConfiguration $configuration): self
    {
        $this->configuration = $configuration;

        return $this;
    }

    /**
     * Set import options from a documented array.
     *
     * Applies import-specific options and merges them with the current configuration.
     * Validates option keys and values before updating configuration.
     *
     * @param  array  $options  Import options including file path, encoding, and processing settings
     * @return self Returns this instance for method chaining
     */
    /** @param array<string,mixed> $options */
    public function withOptions(array $options): self
    {
        $allowed = ['file_path', 'delimiter', 'enclosure', 'escape', 'processing_mode', 'chunk_size', 'skip_rows', 'limit_rows', 'has_headers', 'column_mapping', 'column_types', 'skip_empty_rows', 'trim_values', 'validate_data', 'strict_mode', 'encoding', 'memory_limit', 'unique_fields', 'duplicate_strategy', 'metadata', 'transaction', 'connection', 'field_mappings'];
        if (array_diff(array_keys($options), $allowed) !== []) {
            throw new CSVConfigurationException('Unknown CSV import option.');
        }
        $delimiter = $options['delimiter'] ?? $this->configuration->delimiter;
        if ($delimiter instanceof CSVDelimiterEnum) {
            $delimiter = $delimiter->getCharacter();
        }
        if (! is_string($delimiter)) {
            throw new CSVConfigurationException('CSV delimiter must be a character or delimiter enum.');
        }
        $processingMode = $options['processing_mode'] ?? $this->configuration->processingMode;
        if (is_string($processingMode)) {
            $processingMode = CSVProcessingModeEnum::tryFrom($processingMode);
        }
        if (! $processingMode instanceof CSVProcessingModeEnum) {
            throw new CSVConfigurationException('CSV processing mode is invalid.');
        }
        $encoding = $options['encoding'] ?? null;
        if (is_string($encoding)) {
            $encoding = CSVEncodingEnum::tryFrom($encoding)
                ?? throw new CSVConfigurationException('CSV encoding is invalid.');
        }
        if ($encoding !== null && ! $encoding instanceof CSVEncodingEnum) {
            throw new CSVConfigurationException('CSV encoding is invalid.');
        }
        $duplicateStrategy = $options['duplicate_strategy'] ?? null;
        if (is_string($duplicateStrategy)) {
            $duplicateStrategy = CSVDuplicateStrategyEnum::tryFrom($duplicateStrategy)
                ?? throw new CSVConfigurationException('CSV duplicate strategy is invalid.');
        }
        if ($duplicateStrategy !== null && ! $duplicateStrategy instanceof CSVDuplicateStrategyEnum) {
            throw new CSVConfigurationException('CSV duplicate strategy is invalid.');
        }
        $uniqueFields = self::arrayOption($options, 'unique_fields');
        $fieldMappings = self::arrayOption($options, 'field_mappings');
        self::arrayOption($options, 'column_mapping');
        self::arrayOption($options, 'column_types');
        self::arrayOption($options, 'metadata');
        self::intOption($options, 'skip_rows', 0);
        self::intOption($options, 'limit_rows', null);
        self::boolOption($options, 'validate_data', true);
        $source = $options['file_path'] ?? null;
        if ($source !== null && ! is_string($source)) {
            throw new CSVConfigurationException('CSV option [file_path] must be a string.');
        }
        unset($options['file_path']);
        $this->configuration = new CSVConfiguration(
            delimiter: $delimiter,
            enclosure: self::stringOption($options, 'enclosure', $this->configuration->enclosure),
            escape: self::stringOption($options, 'escape', $this->configuration->escape),
            lineEnding: $this->configuration->lineEnding,
            includeBOM: $this->configuration->includeBOM,
            includeHeaders: self::boolOption($options, 'has_headers', $this->configuration->includeHeaders),
            includeIndex: $this->configuration->includeIndex,
            chunkSize: self::intOption($options, 'chunk_size', $this->configuration->chunkSize),
            processingMode: $processingMode,
            exportFormat: $this->configuration->exportFormat,
            encoding: $this->configuration->encoding,
            memoryLimit: self::intOption($options, 'memory_limit', $this->configuration->memoryLimit),
            strictMode: self::boolOption($options, 'strict_mode', $this->configuration->strictMode),
            skipEmptyRows: self::boolOption($options, 'skip_empty_rows', $this->configuration->skipEmptyRows),
            trimValues: self::boolOption($options, 'trim_values', $this->configuration->trimValues),
        );
        if ($encoding !== null) {
            $this->encoding = $encoding;
        }
        if ($duplicateStrategy !== null) {
            $this->duplicateStrategy = $duplicateStrategy;
        }
        foreach ($uniqueFields as $field) {
            if (! is_string($field)) {
                throw new CSVConfigurationException('Unique fields must be field names.');
            }
            $this->detectDuplicates($field);
        }
        if (isset($options['transaction'])) {
            $this->withTransaction(self::boolOption($options, 'transaction', true));
        }
        if (isset($options['connection'])) {
            $this->onConnection(self::stringOption($options, 'connection', ''));
        }
        $this->applyOptionFieldMappings($options);
        foreach ($fieldMappings as $source => $mapping) {
            if (! is_string($source) || ! $mapping instanceof CSVFieldMapping) {
                throw new CSVConfigurationException('Field mappings require named CSVFieldMapping values.');
            }
            $this->mapField($source, $mapping->targetField, $mapping);
        }

        $this->options = $options + array_intersect_key($this->options, ['file_path' => true]);
        if ($source !== null) {
            $this->fromFile($source);
        }

        return $this;
    }

    /** @param array<string,mixed> $options */
    private static function stringOption(array $options, string $key, string $default): string
    {
        $value = $options[$key] ?? $default;
        if (! is_string($value)) {
            throw new CSVConfigurationException("CSV option [{$key}] must be a string.");
        }

        return $value;
    }

    /** @param array<string,mixed> $options */
    private static function boolOption(array $options, string $key, bool $default): bool
    {
        $value = $options[$key] ?? $default;
        if (! is_bool($value)) {
            throw new CSVConfigurationException("CSV option [{$key}] must be a boolean.");
        }

        return $value;
    }

    /** @param array<string,mixed> $options */
    private static function intOption(array $options, string $key, ?int $default): ?int
    {
        $value = $options[$key] ?? $default;
        if ($value !== null && ! is_int($value)) {
            throw new CSVConfigurationException("CSV option [{$key}] must be an integer or null.");
        }

        return $value;
    }

    /** @param array<string,mixed> $options
     * @return array<array-key,mixed>
     */
    private static function arrayOption(array $options, string $key): array
    {
        $value = $options[$key] ?? [];
        if (! is_array($value)) {
            throw new CSVConfigurationException("CSV option [{$key}] must be an array.");
        }

        return $value;
    }

    /**
     * Set the local file path to import from.
     *
     * Validate a readable regular file and set up import options with the file path.
     * This method is used for importing from local filesystem files.
     *
     * @param  string  $path  Absolute path to the CSV file to import
     * @return self Returns this instance for method chaining
     *
     * @throws CSVFileNotFoundException If the source is not a readable regular file
     * @throws Exception If options cannot be created or updated
     */
    public function fromFile(string $path): self
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new CSVFileNotFoundException('CSV source must be a readable file.');
        }

        $this->options = array_merge($this->options, ['file_path' => $path]);
        $this->sourceDisk = null;
        $this->sourcePath = null;

        return $this;
    }

    /**
     * Set the storage disk and path to import from.
     *
     * Uses Laravel's Storage facade to access files from configured disks
     * (local, s3, etc.). Validates file existence before proceeding.
     *
     * @param  string  $disk  Storage disk name (local, s3, public, etc.)
     * @param  string  $path  Path to the file on the specified disk
     * @return self Returns this instance for method chaining
     *
     * @throws CSVFileNotFoundException If the file does not exist on the specified disk
     * @throws Exception If options cannot be created or disk access fails
     */
    public function fromDisk(string $disk, string $path): self
    {
        $diskInstance = Storage::disk($disk);

        if (! $diskInstance->exists($path)) {
            throw CSVFileNotFoundException::fileNotFoundOnDisk($disk, $path);
        }

        $this->sourceDisk = $disk;
        $this->sourcePath = $path;
        $this->options = array_merge($this->options, ['file_path' => "{$disk}://{$path}"]);

        return $this;
    }

    /**
     * Add a field mapping for CSV column transformation.
     *
     * Maps a CSV column to a target field name with optional transformation rules.
     * If no mapping is provided, creates a simple field name mapping.
     *
     * @param  string  $csvField  Source CSV column header name
     * @param  string  $targetField  Target field name in the output data
     * @param  CSVFieldMapping|null  $mapping  Optional mapping with validation and transformation rules
     * @return self Returns this instance for method chaining
     */
    public function mapField(string $csvField, string $targetField, ?CSVFieldMapping $mapping = null): self
    {
        $fieldMapping = $mapping ?? CSVFieldMapping::simple($csvField, $targetField);
        $this->fieldMappings[$csvField] = $fieldMapping;

        if ($fieldMapping->shouldIndex()) {
            $this->detectDuplicates($csvField);
        }

        return $this;
    }

    /**
     * Add multiple field mappings in batch.
     *
     * Accepts an array of field mappings where keys are CSV column names
     * and values can be either target field names (strings) or full CSVFieldMapping objects.
     *
     * @param  array<string, CSVFieldMapping|string>  $mappings  Array of field mappings
     * @return self Returns this instance for method chaining
     */
    public function mapFields(array $mappings): self
    {
        foreach ($mappings as $csvField => $mapping) {
            if (is_string($mapping)) {
                $this->mapField($csvField, $mapping);
            } else {
                $this->mapField($csvField, $mapping->targetField, $mapping);
            }
        }

        return $this;
    }

    /**
     * Set a custom row processing callback.
     *
     * The callback receives the processed row data and current row number,
     * and can return modified data or perform side effects like database operations.
     *
     * @param  Closure  $processor  Callback function (array $rowData, int $rowNumber) => array|void
     * @return self Returns this instance for method chaining
     */
    public function processRow(Closure $processor): self
    {
        $this->rowProcessor = $processor;

        return $this;
    }

    /**
     * Set a progress monitoring callback.
     *
     * The callback is invoked periodically during import to report progress.
     * Receives current progress data including row counts and memory usage.
     *
     * @param  Closure  $callback  Progress callback function (array $progressData) => void
     * @return self Returns this instance for method chaining
     */
    public function onProgress(Closure $callback): self
    {
        $this->progressCallback = $callback;

        return $this;
    }

    /**
     * Set a custom error handling callback.
     *
     * The callback is invoked when row processing errors occur.
     * Receives the problematic row data, exception, and row number.
     *
     * @param  Closure  $handler  Error callback function (array $rowData, Exception $error, int $rowNumber) => void
     * @return self Returns this instance for method chaining
     */
    public function onError(Closure $handler): self
    {
        $this->errorHandler = $handler;

        return $this;
    }

    /**
     * Configure whether to stop processing on the first error.
     *
     * When enabled, import will halt immediately when an error occurs.
     * When disabled, errors are logged and processing continues.
     *
     * @param  bool  $stop  True to stop on first error, false to continue processing
     * @return self Returns this instance for method chaining
     */
    public function stopOnError(bool $stop = true): self
    {
        $this->stopOnError = $stop;

        return $this;
    }

    /**
     * Set the error level threshold for stopping import.
     *
     * Only errors at or above this level will cause import to stop
     * when stopOnError is enabled. Lower level errors are logged but ignored.
     *
     * @param  CSVErrorLevelEnum  $threshold  Minimum error level to trigger stop
     * @return self Returns this instance for method chaining
     */
    public function withErrorThreshold(CSVErrorLevelEnum $threshold): self
    {
        $this->errorThreshold = $threshold;

        return $this;
    }

    /**
     * Set the strategy for handling duplicate values.
     *
     * Determines how to handle duplicate values when uniqueness constraints
     * are enabled on fields. Options include skip, overwrite, or error.
     *
     * @param  CSVDuplicateStrategyEnum  $strategy  Duplicate handling strategy
     * @return self Returns this instance for method chaining
     */
    public function withDuplicateStrategy(CSVDuplicateStrategyEnum $strategy): self
    {
        $this->duplicateStrategy = $strategy;

        return $this;
    }

    /**
     * Set the file encoding for reading the CSV.
     *
     * Specifies the character encoding of the source CSV file.
     * Common options include UTF-8, UTF-16, and various ISO encodings.
     *
     * @param  CSVEncodingEnum  $encoding  File character encoding
     * @return self Returns this instance for method chaining
     */
    public function withEncoding(CSVEncodingEnum $encoding): self
    {
        $this->encoding = $encoding;

        return $this;
    }

    /**
     * Configure whether to use database transactions.
     *
     * When enabled, the entire import is wrapped in a database transaction
     * that is rolled back on error. Useful for maintaining data consistency.
     *
     * @param  bool  $use  True to use transactions, false to disable
     * @return self Returns this instance for method chaining
     */
    public function withTransaction(bool $use = true): self
    {
        $this->useTransaction = $use;

        return $this;
    }

    /**
     * Select the database connection that owns import and batch transactions.
     *
     * @param  string|null  $connection  Configured Laravel connection name, or null for the default connection
     * @return self Returns this instance for method chaining
     */
    public function onConnection(?string $connection): self
    {
        if ($connection !== null && trim($connection) === '') {
            throw new InvalidArgumentException('CSV transaction connection must be a non-empty string or null.');
        }

        $this->transactionConnection = $connection;

        return $this;
    }

    /**
     * Enable duplicate value detection for a specific field.
     *
     * Maintains an index of values seen for the specified field to detect
     * and handle duplicates according to the configured strategy.
     *
     * @param  string  $field  CSV field name to monitor for duplicates
     * @return self Returns this instance for method chaining
     */
    public function detectDuplicates(string $field): self
    {
        if (! isset($this->uniqueIndexes[$field])) {
            $this->uniqueIndexes[$field] = [];
        }

        return $this;
    }

    /**
     * Get the current duplicate handling strategy.
     *
     * Returns the strategy that will be used when duplicate values
     * are encountered during import processing.
     *
     * @return CSVDuplicateStrategyEnum Current duplicate handling strategy
     */
    public function getDuplicateStrategy(): CSVDuplicateStrategyEnum
    {
        return $this->duplicateStrategy;
    }

    /**
     * Get the current file encoding setting.
     *
     * Returns the character encoding that will be used for reading
     * the CSV file during import.
     *
     * @return CSVEncodingEnum Current file character encoding
     */
    public function getEncoding(): CSVEncodingEnum
    {
        return $this->encoding;
    }

    /**
     * Get the latest progress snapshot for the current import.
     */
    /** @return array<string,mixed>|null */
    public function getProgress(): ?array
    {
        return $this->progress;
    }

    /**
     * Execute the complete CSV import operation.
     *
     * Performs the full import process including file validation, header parsing,
     * data processing, and transaction management. Returns comprehensive results
     * including success/failure statistics and error details.
     *
     * @return array Complete import results with statistics and error information
     *
     * @throws CSVConfigurationException If import configuration is invalid or missing
     * @throws CSVFileNotFoundException If the specified file cannot be found or accessed
     * @throws CSVParseException If CSV structure is invalid or headers are malformed
     * @throws CSVMemoryException If memory limits are exceeded during processing
     * @throws CSVValidationException If data validation rules are violated
     * @throws RuntimeException If file operations or system resources fail
     * @throws Throwable If database transactions fail or other critical errors occur
     */
    /** @return array<string,mixed> */
    private function runImport(): array
    {
        $this->validateConfiguration();
        $this->openFile();

        try {
            $this->readHeaders();
            $this->validateHeaders();

            return $this->useTransaction
                ? $this->transactions->import(
                    $this->transactionConnection,
                    $this->processFile(...),
                )
                : $this->processFile();
        } catch (Throwable $e) {
            $this->progress = ['status' => 'failed', 'error' => $e->getMessage()];
            throw $e;
        } finally {
            $this->closeFile();
        }
    }

    /**
     * Stream import for memory-efficient processing of large CSV files.
     *
     * Returns a Generator that yields processed rows one at a time, allowing
     * for minimal memory usage when processing large files. Each yielded row
     * is fully processed and validated according to field mappings.
     *
     * @return Generator<int, array<string, mixed>> Generator yielding row number => processed row data
     *
     * @throws CSVConfigurationException If import configuration is invalid or missing
     * @throws CSVFileNotFoundException If the specified file cannot be found or accessed
     * @throws RuntimeException If file operations fail or system resources are unavailable
     * @throws CSVParseException If CSV structure is invalid or cannot be parsed
     * @throws CSVMemoryException If memory limits are exceeded during processing
     * @throws CSVValidationException If row data fails validation rules
     * @throws InvalidArgumentException If processing parameters are invalid
     * @throws DivisionByZeroError If progress calculations encounter division by zero
     * @throws Exception If unexpected errors occur during streaming
     */
    public function stream(): Generator
    {
        $this->validateConfiguration();
        $this->openFile();

        try {
            $this->readHeaders();
            $this->validateHeaders();

            $skipRows = $this->options['skip_rows'] ?? 0;
            $limitRows = $this->options['limit_rows'] ?? null;
            $processedRows = 0;

            while (($row = $this->readNextRow()) !== false) {
                if ($this->currentRow <= $skipRows) {
                    continue;
                }

                if ($limitRows !== null && $processedRows >= $limitRows) {
                    break;
                }

                if ($this->shouldSkipRow($row)) {
                    $this->skippedRows++;

                    continue;
                }

                try {
                    $processedRows++;
                    $processedRow = $this->processRowData($row);
                    if ($processedRow === null) {
                        $this->skippedRows++;

                        continue;
                    }
                    $this->successfulRows++;
                    yield $this->currentRow => $processedRow;
                } catch (Exception $e) {
                    $this->handleRowError($row, $e);
                }

                $this->reportProgress();
            }
        } finally {
            $this->closeFile();
        }
    }

    /**
     * Import CSV data in configurable batches for balanced memory usage and performance.
     *
     * Processes the CSV file in chunks of the specified size, calling the batch processor
     * for each chunk. This approach balances memory efficiency with processing performance
     * and allows for custom batch-level operations like database bulk inserts.
     *
     * @param  int  $batchSize  Number of rows to process in each batch
     * @param  Closure  $batchProcessor  Callback function (array $batchData, int $batchNumber) => void
     * @return array Complete import results with batch processing statistics
     *
     * @throws Error If PHP encounters a fatal error during batch processing
     * @throws Exception If batch processor throws an exception or other errors occur
     */
    /** @return array<string,mixed> */
    public function batch(int $batchSize, Closure $batchProcessor): array
    {
        if ($batchSize < 1) {
            throw new InvalidArgumentException('Batch size must be at least 1.');
        }

        $this->validateConfiguration();
        $this->openFile();

        try {
            $this->readHeaders();
            $this->validateHeaders();

            $skipRows = $this->options['skip_rows'] ?? 0;
            $limitRows = $this->options['limit_rows'] ?? null;
            $processedRows = 0;

            $batch = [];
            $batchNumber = 0;

            while (($row = $this->readNextRow()) !== false) {
                if ($this->currentRow <= $skipRows) {
                    continue;
                }

                if ($limitRows !== null && $processedRows >= $limitRows) {
                    break;
                }

                if ($this->shouldSkipRow($row)) {
                    $this->skippedRows++;

                    continue;
                }

                try {
                    $processedRows++;
                    $processedRow = $this->processRowData($row);
                    if ($processedRow === null) {
                        $this->skippedRows++;

                        continue;
                    }
                    $batch[] = $processedRow;

                    if (count($batch) >= $batchSize) {
                        $this->processBatch($batch, $batchProcessor, ++$batchNumber);
                        $batch = [];
                    }
                } catch (Exception $e) {
                    $this->handleRowError($row, $e);
                }
            }

            // Process remaining batch
            if (! empty($batch)) {
                $this->processBatch($batch, $batchProcessor, ++$batchNumber);
            }

            return $this->createResult();
        } finally {
            $this->closeFile();
        }
    }

    /**
     * Validate the import configuration and options before processing.
     *
     * Ensures all required configuration parameters are present and valid.
     * Sets up default options if none provided and configures memory management.
     *
     * @throws CSVConfigurationException If required configuration is missing or invalid
     */
    private function validateConfiguration(): void
    {

        if (empty($this->options['file_path'])) {
            throw CSVConfigurationException::missingConfiguration('filePath');
        }

        $this->resetProcessingState();

        if ($this->configuration->memoryLimit !== null) {
            $this->memoryManager->setLimit($this->configuration->memoryLimit);
        }
    }

    /**
     * Open the CSV file for reading and prepare file handle.
     *
     * Opens the specified file, validates it exists and is accessible,
     * and handles UTF-8 BOM detection and skipping if present.
     *
     * @throws CSVFileNotFoundException If the file path is invalid or file doesn't exist
     * @throws RuntimeException If the file cannot be opened for reading
     * @throws CSVConfigurationException If file path configuration is missing
     */
    private function openFile(): void
    {
        $filePath = self::stringOption($this->options, 'file_path', '');
        $handle = $this->sourceDisk !== null && $this->sourcePath !== null
            ? Storage::disk($this->sourceDisk)->readStream($this->sourcePath)
            : fopen($filePath, 'rb');

        if (! is_resource($handle)) {
            throw new RuntimeException("Unable to open file: $filePath");
        }

        $this->handle = $handle;
        $this->prepareInputStream();
    }

    /**
     * Strip a supported BOM and convert the input stream to UTF-8.
     */
    private function prepareInputStream(): void
    {
        if (! is_resource($this->handle)) {
            throw new RuntimeException('File handle is not open');
        }

        $prefix = fread($this->handle, 4);
        $prefix = $prefix === false ? '' : $prefix;
        $detectedEncoding = CSVEncodingEnum::detectFromBOM($prefix);
        $sourceEncoding = $detectedEncoding ?? $this->encoding;
        $bomLength = $detectedEncoding === null ? 0 : strlen($detectedEncoding->getBOM());

        $metadata = stream_get_meta_data($this->handle);
        if ($metadata['seekable']) {
            rewind($this->handle);
        } else {
            $this->closeFile();
            $this->reopenSource();
        }

        $handle = $this->handle;
        if (! is_resource($handle)) {
            throw new RuntimeException('Unable to prepare CSV input stream.');
        }

        if ($bomLength > 0) {
            fread($handle, $bomLength);
        }

        $phpEncoding = $sourceEncoding->getPhpEncoding();
        if ($phpEncoding !== 'UTF-8') {
            $filter = stream_filter_append(
                $handle,
                "convert.iconv.{$phpEncoding}/UTF-8",
                STREAM_FILTER_READ
            );

            if ($filter === false) {
                throw new RuntimeException("Unable to convert CSV input from {$phpEncoding} to UTF-8.");
            }
        }
    }

    /**
     * Reopen a non-seekable source stream.
     */
    private function reopenSource(): void
    {
        $filePath = self::stringOption($this->options, 'file_path', '');
        $handle = $this->sourceDisk !== null && $this->sourcePath !== null
            ? Storage::disk($this->sourceDisk)->readStream($this->sourcePath)
            : fopen($filePath, 'rb');

        if (! is_resource($handle)) {
            throw new RuntimeException('Unable to reopen CSV input stream.');
        }

        $this->handle = $handle;
    }

    /**
     * Close the CSV file handle and clean up resources.
     *
     * Safely closes the file handle if it's currently open and resets
     * the handle to null to prevent further operations.
     */
    private function closeFile(): void
    {
        if (is_resource($this->handle)) {
            fclose($this->handle);
        }
        $this->handle = null;
    }

    /**
     * Read and parse CSV column headers from the file.
     *
     * Reads the first row as headers if configured, or generates generic
     * column names if headers are disabled. Handles header trimming and
     * validates that headers are present and valid.
     *
     * @throws RuntimeException If the file handle is not open or cannot be read
     * @throws CSVParseException If headers are invalid, missing, or cannot be parsed
     */
    private function readHeaders(): void
    {
        if (! is_resource($this->handle)) {
            throw new RuntimeException('File handle is not open');
        }

        if ($this->configuration->includeHeaders) {
            // Read first row as headers
            $headers = fgetcsv(
                $this->handle,
                0,
                $this->configuration->delimiter,
                $this->configuration->enclosure,
                $this->configuration->escape
            );

            if ($headers === false) {
                throw CSVParseException::invalidHeaders();
            }
            $this->assertValidUtf8($headers, 1);

            // Trim headers if configured and filter out nulls
            if ($this->configuration->trimValues) {
                $headers = array_map(fn ($h) => is_string($h) ? trim($h) : (string) $h, $headers);
            }

            // Ensure all headers are strings
            $this->headers = array_map(fn ($h) => (string) $h, $headers);
        } else {
            $firstRow = fgetcsv(
                $this->handle,
                0,
                $this->configuration->delimiter,
                $this->configuration->enclosure,
                $this->configuration->escape
            );

            if ($firstRow === false) {
                throw CSVParseException::invalidHeaders();
            }
            $this->assertValidUtf8($firstRow, 1);

            $this->pendingRow = $firstRow;

            // Generate generic column names: col_0, col_1, col_2, etc.
            $columnCount = count($firstRow);
            $this->headers = array_map(fn ($i) => "col_$i", range(0, $columnCount - 1));
        }
    }

    /**
     * Validate that all required field mappings have corresponding headers.
     *
     * Checks that all required CSV fields defined in field mappings exist
     * in the parsed headers. Missing required fields will cause validation to fail.
     *
     * @throws CSVValidationException If required field mappings are missing from CSV headers
     */
    private function validateHeaders(): void
    {
        if (
            $this->headers === []
            || in_array('', $this->headers, true)
            || count($this->headers) !== count(array_unique($this->headers))
        ) {
            throw CSVParseException::invalidHeaders();
        }

        if (empty($this->fieldMappings)) {
            return;
        }

        $missingFields = [];

        foreach ($this->fieldMappings as $csvField => $mapping) {
            if (! in_array($csvField, $this->headers, true)) {
                if ($mapping->required) {
                    $missingFields[] = $csvField;
                }
            }
        }

        if (! empty($missingFields)) {
            throw CSVValidationException::missingRequiredFields($missingFields);
        }
    }

    /**
     * Read the next row of data from the CSV file.
     *
     * Reads and parses the next CSV row using the configured delimiter, enclosure,
     * and escape characters. Tracks current row number and monitors memory usage.
     *
     * @return array<int, string|null>|false Array of column values, or false if end of file reached
     *
     * @throws CSVMemoryException If reading the row would exceed memory limits
     */
    private function readNextRow(): array|false
    {
        if (! is_resource($this->handle)) {
            return false;
        }

        $row = $this->pendingRow ?? fgetcsv(
            $this->handle,
            0,
            $this->configuration->delimiter,
            $this->configuration->enclosure,
            $this->configuration->escape
        );
        $this->pendingRow = null;

        if ($row === false) {
            return false;
        }

        $this->currentRow++;
        $this->assertValidUtf8($row, $this->currentRow);
        if ($this->configuration->trimValues) {
            $row = array_map(static fn (?string $value): ?string => $value === null ? null : trim($value), $row);
        }

        // Check memory before processing
        if (! $this->memoryManager->canAllocate(1024)) {
            throw CSVMemoryException::memoryLimitExceeded(
                $this->memoryManager->getCurrentUsage(),
                $this->memoryManager->getLimit()
            );
        }

        return $row;
    }

    /** @param array<int,string|null> $row */
    private function assertValidUtf8(array $row, int $line): void
    {
        foreach ($row as $value) {
            if (is_string($value) && ! mb_check_encoding($value, 'UTF-8')) {
                throw CSVParseException::invalidEncoding('UTF-8', $line);
            }
        }
    }

    /**
     * Determine if a row should be skipped during processing.
     *
     * Checks if the row meets skip criteria such as being entirely empty
     * when empty row skipping is enabled in the configuration.
     *
     * @param  array<int, string|null>  $row  Raw row data from CSV
     * @return bool True if the row should be skipped, false to process it
     */
    private function shouldSkipRow(array $row): bool
    {
        // Skip empty rows
        if ($this->configuration->skipEmptyRows) {
            $nonEmpty = array_filter($row, fn ($value) => $value !== '' && $value !== null);
            if (empty($nonEmpty)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Process and transform raw CSV row data according to field mappings.
     *
     * Applies field mappings, validation rules, type casting, and custom transformations
     * to convert raw CSV data into the target format. Handles duplicate detection
     * and executes custom row processors if configured.
     *
     * @param  array<int, string|null>  $row  Raw CSV row data
     * @return array<string, mixed>|null Processed row data, or null when a duplicate is skipped
     *
     * @throws CSVValidationException If row data fails validation rules
     * @throws InvalidArgumentException If field mapping or transformation parameters are invalid
     */
    private function processRowData(array $row): ?array
    {
        /** @var array<string, mixed> $processedData */
        $processedData = [];

        if (count($row) !== count($this->headers)) {
            if ($this->configuration->strictMode) {
                throw CSVParseException::columnCountMismatch(
                    count($this->headers),
                    count($row),
                    $this->currentRow,
                );
            }

            if (count($row) < count($this->headers)) {
                $row = array_pad($row, count($this->headers), '');
            } else {
                $row = array_slice($row, 0, count($this->headers));
            }
        }

        // Map row to associative array using headers
        $rowData = array_combine($this->headers, $row) ?: [];

        // If no field mappings defined, use raw associative array
        if (empty($this->fieldMappings)) {
            $processedData = $rowData;
        } else {
            // Apply field mappings
            foreach ($this->fieldMappings as $csvField => $mapping) {
                $value = $rowData[$csvField] ?? $mapping->defaultValue;

                if ($this->shouldValidateData() && ! $mapping->validate($value)) {
                    $errors = $mapping->getValidationErrors($value);
                    throw new CSVValidationException(implode(', ', $errors));
                }

                // Apply transformation
                $value = $mapping->apply($value);

                $processedData[$mapping->targetField] = $value;
            }
        }

        $uniqueValues = [];
        foreach (array_keys($this->uniqueIndexes) as $csvField) {
            $mapping = $this->fieldMappings[$csvField] ?? null;
            $value = $mapping === null
                ? ($rowData[$csvField] ?? null)
                : ($processedData[$mapping->targetField] ?? null);

            $indexKey = $this->uniqueValueKey($value);
            if (in_array($value, $this->uniqueIndexes[$csvField][$indexKey] ?? [], true)) {
                if ($this->duplicateStrategy === CSVDuplicateStrategyEnum::SKIP) {
                    return null;
                }

                if ($this->duplicateStrategy === CSVDuplicateStrategyEnum::ERROR) {
                    throw new CSVValidationException(
                        "Duplicate value for field '{$csvField}': ".$this->describeValue($value)
                    );
                }

                continue;
            }

            $uniqueValues[$csvField] = [$indexKey, $value];
        }

        foreach ($uniqueValues as $csvField => [$indexKey, $value]) {
            $this->uniqueIndexes[$csvField][$indexKey][] = $value;
        }

        // Apply custom row processor if set
        if ($this->rowProcessor !== null) {
            $result = ($this->rowProcessor)($processedData, $this->currentRow);
            if (is_array($result)) {
                /** @var array<string, mixed> $processedData */
                $processedData = $result;
            }
        }

        $this->activeHandler?->process($processedData, $this->currentRow);

        return $processedData;
    }

    /**
     * Process a batch of rows using the configured batch processor.
     *
     * Executes the batch processor callback within a transaction context,
     * handles errors, and updates processing statistics. The batch is processed
     * as a single unit for better performance and transactional consistency.
     *
     * @param  array<array<string, mixed>>  $batch  Array of processed row data
     * @param  Closure  $batchProcessor  Callback function to process the batch
     * @param  int  $batchNumber  Sequential batch number for tracking
     *
     * @throws Error If PHP encounters a fatal error during batch processing
     * @throws Exception If the batch processor throws an exception
     */
    private function processBatch(array $batch, Closure $batchProcessor, int $batchNumber): void
    {
        try {
            if ($this->useTransaction) {
                $this->transactions->batch(
                    $this->transactionConnection,
                    function () use ($batch, $batchNumber, $batchProcessor): void {
                        $batchProcessor($batch, $batchNumber);
                    },
                );
            } else {
                $batchProcessor($batch, $batchNumber);
            }

            $this->successfulRows += count($batch);
        } catch (Exception $e) {
            $this->failedRows += count($batch);
            $this->recordError([
                'level' => CSVErrorLevelEnum::ERROR,
                'message' => "Batch $batchNumber failed: ".$e->getMessage(),
            ]);

            if (
                $this->stopOnError
                && CSVErrorLevelEnum::ERROR->meetsThreshold($this->errorThreshold)
            ) {
                throw $e;
            }
        }
    }

    /**
     * Handle errors that occur during row processing.
     *
     * Records error details, updates statistics, and determines whether to continue
     * or stop processing based on error level and configuration. Invokes custom
     * error handlers if configured.
     *
     * @param  array<int, string|null>  $row  Raw row data that caused the error
     * @param  Exception  $e  Exception that was thrown during processing
     *
     * @throws DivisionByZeroError If progress calculation encounters division by zero
     * @throws Exception If error threshold is exceeded and stopOnError is enabled
     */
    private function handleRowError(array $row, Exception $e): void
    {
        $this->failedRows++;
        $errorLevel = match (true) {
            $e instanceof CSVValidationException => CSVErrorLevelEnum::ERROR,
            $e instanceof CSVMemoryException => CSVErrorLevelEnum::CRITICAL,
            $e instanceof InvalidArgumentException => CSVErrorLevelEnum::ERROR,
            $e instanceof QueryException => CSVErrorLevelEnum::ERROR,
            default => CSVErrorLevelEnum::WARNING,
        };

        if (count($this->failedRowsData) < self::MAX_RETAINED_FAILURES) {
            $this->failedRowsData[] = [
                'row_number' => $this->currentRow,
                'data' => $row,
                'error' => $e->getMessage(),
            ];
        }

        $this->recordError([
            'level' => $errorLevel,
            'message' => "Row {$this->currentRow}: ".$e->getMessage(),
            'row' => $this->currentRow,
        ]);

        // Update progress if tracking
        if ($this->progress !== null) {
            $this->progress['failed_rows'] = $this->failedRows;
            $this->progress['last_error'] = $e->getMessage();
        }

        if ($this->errorHandler !== null) {
            ($this->errorHandler)($row, $e, $this->currentRow);
        }

        if ($errorLevel->meetsThreshold($this->errorThreshold) && $this->stopOnError) {
            throw $e;
        }
    }

    /**
     * Retain bounded diagnostics while preserving complete failure counters.
     *
     * @param  array{level: CSVErrorLevelEnum, message: string, row?: int|null}  $error
     */
    private function recordError(array $error): void
    {
        if (count($this->errors) < self::MAX_RETAINED_FAILURES) {
            $this->errors[] = $error;

            return;
        }

        if (! $this->diagnosticsTruncated) {
            $this->warnings[] = [
                'level' => CSVErrorLevelEnum::WARNING,
                'message' => 'Additional CSV failure details were omitted after the retention limit was reached.',
            ];
            $this->diagnosticsTruncated = true;
        }
    }

    /**
     * Report import progress to configured callbacks and update internal tracking.
     *
     * Updates progress data with current statistics and invokes progress callbacks
     * at regular intervals. Provides real-time feedback on import status including
     * row counts, memory usage, and processing speed.
     *
     * @throws DivisionByZeroError If progress percentage calculation fails
     * @throws Exception If progress callback throws an exception
     */
    private function reportProgress(): void
    {
        $this->progress = ['status' => 'running', 'processed_rows' => $this->currentRow,
            'successful_rows' => $this->successfulRows, 'failed_rows' => $this->failedRows,
            'skipped_rows' => $this->skippedRows];

        // Call user callback
        if ($this->progressCallback !== null && $this->currentRow % 100 === 0) {
            $progressData = $this->progress ?? [
                'current_row' => $this->currentRow,
                'successful' => $this->successfulRows,
                'failed' => $this->failedRows,
                'skipped' => $this->skippedRows,
                'memory_usage' => $this->memoryManager->getCurrentUsage(),
            ];

            ($this->progressCallback)($progressData);
        }
    }

    /**
     * Process the entire CSV file row by row.
     *
     * Main processing loop that reads each row, applies transformations,
     * handles errors, and tracks progress. Used by the main import() method
     * for complete file processing.
     *
     * @return array Final import results with complete statistics
     *
     * @throws CSVMemoryException If memory limits are exceeded during processing
     * @throws CSVValidationException If row validation fails
     * @throws InvalidArgumentException If processing parameters are invalid
     * @throws Exception If unexpected errors occur during processing
     * @throws DivisionByZeroError If progress calculations encounter division by zero
     */
    /** @return array<string,mixed> */
    private function processFile(): array
    {
        $skipRows = $this->options['skip_rows'] ?? 0;
        $limitRows = $this->options['limit_rows'] ?? null;
        $processedRows = 0;

        while (($row = $this->readNextRow()) !== false) {
            if ($this->currentRow <= $skipRows) {
                continue;
            }

            if ($limitRows !== null && $processedRows >= $limitRows) {
                break;
            }

            if ($this->shouldSkipRow($row)) {
                $this->skippedRows++;

                continue;
            }

            try {
                $processedRows++;
                $database = DB::connection($this->transactionConnection);
                $processedRow = $database->transactionLevel() > 0
                    ? $database->transaction(fn (): ?array => $this->processRowData($row))
                    : $this->processRowData($row);
                if ($processedRow === null) {
                    $this->skippedRows++;

                    continue;
                }

                $this->successfulRows++;
            } catch (Exception $e) {
                $this->handleRowError($row, $e);
            }

            $this->reportProgress();
        }

        return $this->createResult();
    }

    /**
     * Create the final import result with comprehensive statistics and metadata.
     *
     * Compiles all processing statistics, error information, timing data,
     * and metadata into a structured result object that provides complete
     * information about the import operation.
     *
     * @return array Complete import result with statistics, errors, and metadata
     */
    /** @return array<string,mixed> */
    private function createResult(): array
    {
        $processingTime = microtime(true) - $this->startTime;
        $this->progress = ['status' => 'complete', 'processed_rows' => $this->currentRow];
        $metadata = $this->options['metadata'] ?? null;
        $optionMetadata = is_array($metadata) ? $metadata : [];

        return [
            'total_rows' => $this->currentRow,
            'processed_rows' => $this->successfulRows + $this->failedRows,
            'successful_rows' => $this->successfulRows,
            'failed_rows' => $this->failedRows,
            'skipped_rows' => $this->skippedRows,
            'failed_rows_data' => $this->failedRowsData,
            'processing_time' => $processingTime,
            'started_at' => Carbon::createFromTimestamp($this->startTime),
            'completed_at' => Carbon::now(),
            'errors' => array_values(array_map(fn ($e) => $e['message'], $this->errors)),
            'warnings' => array_values(array_map(fn ($w) => $w['message'], $this->warnings)),
            'metadata' => array_merge($optionMetadata, [
                'memory_peak' => $this->memoryManager->getPeakUsage(),
                'file_path' => $this->options['file_path'] ?? null,
            ]),
        ];
    }

    /**
     * Build fluent field mappings from structured DTO options.
     */
    /** @param array<string,mixed> $options */
    private function applyOptionFieldMappings(array $options): void
    {
        $columnMappings = self::arrayOption($options, 'column_mapping');
        $columnTypes = self::arrayOption($options, 'column_types');
        $sourceFields = $this->optionSourceFields($columnMappings, $columnTypes);

        foreach ($sourceFields as $sourceField) {
            $configuredMapping = $columnMappings[$sourceField] ?? $sourceField;
            if ($configuredMapping instanceof CSVFieldMapping) {
                $mapping = $configuredMapping;
            } elseif (is_string($configuredMapping) && $configuredMapping !== '') {
                $mapping = CSVFieldMapping::simple($sourceField, $configuredMapping);
            } else {
                throw new CSVConfigurationException(
                    "Column mapping for '{$sourceField}' must be a target field name or CSVFieldMapping.",
                );
            }

            if (array_key_exists($sourceField, $columnTypes)) {
                $type = $columnTypes[$sourceField];
                if (is_string($type)) {
                    $type = CSVTypeEnum::tryFrom($type);
                }

                if (! $type instanceof CSVTypeEnum) {
                    throw new CSVConfigurationException(
                        "Column type for '{$sourceField}' must be a CSVTypeEnum or valid type value.",
                    );
                }

                $mapping = new CSVFieldMapping(
                    sourceField: $mapping->sourceField,
                    targetField: $mapping->targetField,
                    type: $type,
                    required: $mapping->required,
                    defaultValue: $mapping->defaultValue,
                    transformer: $mapping->transformer,
                    validators: $mapping->validators,
                    unique: $mapping->unique,
                    nullable: $mapping->nullable,
                    format: $mapping->format,
                    metadata: $mapping->metadata,
                );
            }

            $this->mapField($sourceField, $mapping->targetField, $mapping);
        }
    }

    /**
     * Collect and validate source fields configured through DTO options.
     *
     * @param  array<array-key, mixed>  $columnMappings
     * @param  array<array-key, mixed>  $columnTypes
     * @return list<string>
     */
    private function optionSourceFields(array $columnMappings, array $columnTypes): array
    {
        $sourceFields = [];

        foreach ([$columnMappings, $columnTypes] as $options) {
            foreach ($options as $sourceField => $value) {
                if (! is_string($sourceField) || $sourceField === '') {
                    throw new CSVConfigurationException('Column mapping keys must be non-empty strings.');
                }

                $sourceFields[$sourceField] = true;
            }
        }

        return array_keys($sourceFields);
    }

    /**
     * Determine whether configured field validators should run.
     */
    private function shouldValidateData(): bool
    {
        return self::boolOption($this->options, 'validate_data', true);
    }

    /**
     * Reset operation-specific state while preserving fluent configuration.
     */
    private function resetProcessingState(): void
    {
        $this->startTime = microtime(true);
        $this->currentRow = 0;
        $this->successfulRows = 0;
        $this->failedRows = 0;
        $this->skippedRows = 0;
        $this->failedRowsData = [];
        $this->errors = [];
        $this->warnings = [];
        $this->diagnosticsTruncated = false;
        $this->headers = [];
        $this->pendingRow = null;

        foreach (array_keys($this->uniqueIndexes) as $field) {
            $this->uniqueIndexes[$field] = [];
        }

        $this->progress = ['status' => 'pending', 'processed_rows' => 0];
    }

    /** Bucket scalar values without serializing objects or changing strict compound comparisons. */
    private function uniqueValueKey(mixed $value): string
    {
        if (is_scalar($value) || $value === null) {
            return serialize(is_float($value) && $value === 0.0 ? 0.0 : $value);
        }

        return 'compound';
    }

    /**
     * Render a diagnostic value without unsafe implicit string conversion.
     */
    private function describeValue(mixed $value): string
    {
        if (is_scalar($value) || $value === null) {
            return var_export($value, true);
        }

        $encoded = json_encode($value);

        return $encoded === false ? get_debug_type($value) : $encoded;
    }
}
