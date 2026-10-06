<?php

declare(strict_types=1);

namespace Nvl\Csv\Contracts;

use Closure;
use DivisionByZeroError;
use Error;
use Exception;
use Generator;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Nvl\Csv\Data\CSVImportOptionsData;
use Nvl\Csv\Data\CSVProgressData;
use Nvl\Csv\Enums\CSVDuplicateStrategyEnum;
use Nvl\Csv\Enums\CSVEncodingEnum;
use Nvl\Csv\Enums\CSVErrorLevelEnum;
use Nvl\Csv\Exceptions\CSVConfigurationException;
use Nvl\Csv\Exceptions\CSVFileNotFoundException;
use Nvl\Csv\Exceptions\CSVMemoryException;
use Nvl\Csv\Exceptions\CSVParseException;
use Nvl\Csv\Exceptions\CSVValidationException;
use Nvl\Csv\ValueObjects\CSVConfiguration;
use Nvl\Csv\ValueObjects\CSVFieldMapping;
use Nvl\Csv\ValueObjects\CSVImportResult;
use RuntimeException;
use Spatie\LaravelData\Optional;
use Throwable;

/**
 * Defines the consumer-facing CSVImport workflow.
 *
 * @api
 */
interface CSVImportContract
{
    /**
     * Set the CSV import configuration.
     *
     * Replaces the current configuration with the provided one.
     * Used to customize parsing behavior, memory limits, and processing options.
     *
     * @param  CSVConfiguration  $configuration  New configuration settings
     * @return self Returns this instance for method chaining
     */
    public function configure(CSVConfiguration $configuration): self;

    /**
     * Set import options from a structured DTO.
     *
     * Applies import-specific options and merges them with the current configuration.
     * Handles Optional values from Spatie Data and updates configuration accordingly.
     *
     * @param  CSVImportOptionsData  $options  Import options including file path, encoding, and processing settings
     * @return self Returns this instance for method chaining
     */
    public function withOptions(CSVImportOptionsData $options): self;

    /**
     * Set the local file path to import from.
     *
     * Validates that the file exists and sets up import options with the file path.
     * This method is used for importing from local filesystem files.
     *
     * @param  string  $path  Absolute path to the CSV file to import
     * @return self Returns this instance for method chaining
     *
     * @throws CSVFileNotFoundException If the specified file does not exist
     * @throws Exception If options cannot be created or updated
     */
    public function fromFile(string $path): self;

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
    public function fromDisk(string $disk, string $path): self;

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
    public function mapField(string $csvField, string $targetField, ?CSVFieldMapping $mapping = null): self;

    /**
     * Add multiple field mappings in batch.
     *
     * Accepts an array of field mappings where keys are CSV column names
     * and values can be either target field names (strings) or full CSVFieldMapping objects.
     *
     * @param  array<string, CSVFieldMapping|string>  $mappings  Array of field mappings
     * @return self Returns this instance for method chaining
     */
    public function mapFields(array $mappings): self;

    /**
     * Set a custom row processing callback.
     *
     * The callback receives the processed row data and current row number,
     * and can return modified data or perform side effects like database operations.
     *
     * @param  Closure  $processor  Callback function (array $rowData, int $rowNumber) => array|void
     * @return self Returns this instance for method chaining
     */
    public function processRow(Closure $processor): self;

    /**
     * Set a progress monitoring callback.
     *
     * The callback is invoked periodically during import to report progress.
     * Receives current progress data including row counts and memory usage.
     *
     * @param  Closure  $callback  Progress callback function (array $progressData) => void
     * @return self Returns this instance for method chaining
     */
    public function onProgress(Closure $callback): self;

    /**
     * Set a custom error handling callback.
     *
     * The callback is invoked when row processing errors occur.
     * Receives the problematic row data, exception, and row number.
     *
     * @param  Closure  $handler  Error callback function (array $rowData, Exception $error, int $rowNumber) => void
     * @return self Returns this instance for method chaining
     */
    public function onError(Closure $handler): self;

    /**
     * Configure whether to stop processing on the first error.
     *
     * When enabled, import will halt immediately when an error occurs.
     * When disabled, errors are logged and processing continues.
     *
     * @param  bool  $stop  True to stop on first error, false to continue processing
     * @return self Returns this instance for method chaining
     */
    public function stopOnError(bool $stop = true): self;

    /**
     * Set the error level threshold for stopping import.
     *
     * Only errors at or above this level will cause import to stop
     * when stopOnError is enabled. Lower level errors are logged but ignored.
     *
     * @param  CSVErrorLevelEnum  $threshold  Minimum error level to trigger stop
     * @return self Returns this instance for method chaining
     */
    public function withErrorThreshold(CSVErrorLevelEnum $threshold): self;

    /**
     * Set the strategy for handling duplicate values.
     *
     * Determines how to handle duplicate values when uniqueness constraints
     * are enabled on fields. Options include skip, overwrite, or error.
     *
     * @param  CSVDuplicateStrategyEnum  $strategy  Duplicate handling strategy
     * @return self Returns this instance for method chaining
     */
    public function withDuplicateStrategy(CSVDuplicateStrategyEnum $strategy): self;

    /**
     * Set the file encoding for reading the CSV.
     *
     * Specifies the character encoding of the source CSV file.
     * Common options include UTF-8, UTF-16, and various ISO encodings.
     *
     * @param  CSVEncodingEnum  $encoding  File character encoding
     * @return self Returns this instance for method chaining
     */
    public function withEncoding(CSVEncodingEnum $encoding): self;

    /**
     * Configure whether to use database transactions.
     *
     * When enabled, the entire import is wrapped in a database transaction
     * that is rolled back on error. Useful for maintaining data consistency.
     *
     * @param  bool  $use  True to use transactions, false to disable
     * @return self Returns this instance for method chaining
     */
    public function withTransaction(bool $use = true): self;

    /**
     * Select the database connection that owns import and batch transactions.
     *
     * @param  string|null  $connection  Configured Laravel connection name, or null for the default connection
     * @return self Returns this instance for method chaining
     */
    public function onConnection(?string $connection): self;

    /**
     * Enable duplicate value detection for a specific field.
     *
     * Maintains an index of values seen for the specified field to detect
     * and handle duplicates according to the configured strategy.
     *
     * @param  string  $field  CSV field name to monitor for duplicates
     * @return self Returns this instance for method chaining
     */
    public function detectDuplicates(string $field): self;

    /**
     * Get the current duplicate handling strategy.
     *
     * Returns the strategy that will be used when duplicate values
     * are encountered during import processing.
     *
     * @return CSVDuplicateStrategyEnum Current duplicate handling strategy
     */
    public function getDuplicateStrategy(): CSVDuplicateStrategyEnum;

    /**
     * Get the current file encoding setting.
     *
     * Returns the character encoding that will be used for reading
     * the CSV file during import.
     *
     * @return CSVEncodingEnum Current file character encoding
     */
    public function getEncoding(): CSVEncodingEnum;

    /**
     * Get the latest progress snapshot for the current import.
     */
    public function getProgress(): ?CSVProgressData;

    /**
     * Execute the complete CSV import operation.
     *
     * Performs the full import process including file validation, header parsing,
     * data processing, and transaction management. Returns comprehensive results
     * including success/failure statistics and error details.
     *
     * @return CSVImportResult Complete import results with statistics and error information
     *
     * @throws CSVConfigurationException If import configuration is invalid or missing
     * @throws CSVFileNotFoundException If the specified file cannot be found or accessed
     * @throws CSVParseException If CSV structure is invalid or headers are malformed
     * @throws CSVMemoryException If memory limits are exceeded during processing
     * @throws CSVValidationException If data validation rules are violated
     * @throws RuntimeException If file operations or system resources fail
     * @throws Throwable If database transactions fail or other critical errors occur
     */
    public function import(): CSVImportResult;

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
    public function stream(): Generator;

    /**
     * Import CSV data in configurable batches for balanced memory usage and performance.
     *
     * Processes the CSV file in chunks of the specified size, calling the batch processor
     * for each chunk. This approach balances memory efficiency with processing performance
     * and allows for custom batch-level operations like database bulk inserts.
     *
     * @param  int  $batchSize  Number of rows to process in each batch
     * @param  Closure  $batchProcessor  Callback function (array $batchData, int $batchNumber) => void
     * @return CSVImportResult Complete import results with batch processing statistics
     *
     * @throws Error If PHP encounters a fatal error during batch processing
     * @throws Exception If batch processor throws an exception or other errors occur
     */
    public function batch(int $batchSize, Closure $batchProcessor): CSVImportResult;
}
