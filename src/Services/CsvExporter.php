<?php

declare(strict_types=1);

namespace Nvl\Csv\Services;

use BackedEnum;
use Closure;
use DateTimeInterface;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use JsonException;
use Nvl\Csv\Enums\CSVDelimiterEnum;
use Nvl\Csv\Enums\CSVEncodingEnum;
use Nvl\Csv\Enums\CSVExportFormatEnum;
use Nvl\Csv\Enums\CSVProcessingModeEnum;
use Nvl\Csv\Exceptions\CSVConfigurationException;
use Nvl\Csv\Exceptions\CSVMemoryException;
use Nvl\Csv\Support\CSVMemoryManager;
use Nvl\Csv\ValueObjects\CSVConfiguration;
use RuntimeException;
use Stringable;
use Throwable;

/**
 * Enhanced CSV export service with fluent API
 *
 * @throws CSVConfigurationException
 * @throws CSVMemoryException
 * @throws RuntimeException
 *
 * @api
 */
final class CsvExporter
{
    private CSVConfiguration $configuration;

    /** @var array<string,mixed> */
    private array $options = [];

    private CSVMemoryManager $memoryManager;

    /** @var resource|null */
    private $handle = null;

    private float $startTime;

    private int $rowCount = 0;

    private int $columnCount = 0;

    /** @var array<string> */
    private array $errors = [];

    /** @var array<string> */
    private array $warnings = [];

    /** @var list<string|Closure> */
    private array $fields = [];

    /** @var list<string>|null */
    private ?array $inferredFields = null;

    private bool $headersWritten = false;

    /**
     * Create a new CSV export service instance.
     *
     * Initializes the export service with configuration, memory management,
     * and timing. If no configuration is provided, uses default settings.
     *
     * @param  CSVConfiguration|null  $configuration  Export configuration settings (null = use defaults)
     * @return void
     */
    public function __construct(?CSVConfiguration $configuration = null)
    {
        $this->configuration = $configuration ?? CSVConfiguration::default();
        $this->memoryManager = new CSVMemoryManager;
        $this->startTime = microtime(true);
    }

    /**
     * Stream rows through the CSV writer and return encoded bytes.
     *
     * @param  iterable<array-key,array<string,mixed>|Model>  $rows
     * @param  array<string,mixed>  $options
     */
    public function export(iterable $rows, array $options): string
    {
        $this->withOptions(['filename' => 'export.csv', ...$options]);
        $this->validateConfiguration();
        $this->openFile();

        try {
            $this->writeHeaders();
            $this->writeCollectionData($rows);
            $output = $this->buildOutputStream();
            try {
                $statistics = fstat($output);
                $bytes = is_array($statistics) ? $statistics['size'] : null;
                if (! is_int($bytes) || $bytes < 0) {
                    throw new RuntimeException('Unable to determine CSV output size.');
                }
                $required = $bytes > intdiv(PHP_INT_MAX, 2) ? PHP_INT_MAX : $bytes * 2;
                $this->memoryManager->assertCanAllocate($required);
                $contents = stream_get_contents($output);
                if (! is_string($contents)) {
                    throw new RuntimeException('Unable to read CSV output.');
                }

                return $contents;
            } finally {
                fclose($output);
            }
        } finally {
            $this->closeFile();
        }
    }

    /**
     * Create a new export instance with fluent API.
     *
     * Static factory method for creating CSV export instances using
     * a fluent interface pattern for easy method chaining.
     *
     * @return self New CSV export instance with default configuration
     */
    public static function make(): self
    {
        return new self;
    }

    /**
     * Set the CSV export configuration.
     *
     * Replaces the current configuration with the provided one.
     * Used to customize formatting, delimiters, encoding, and processing options.
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
     * Set export options from a documented array.
     *
     * Applies export-specific options including output path, filename,
     * column headings, and field mappings from the caller.
     *
     * @param  array  $options  Export options including path, filename, and field configuration
     * @return self Returns this instance for method chaining
     */
    /** @param array<string,mixed> $options */
    public function withOptions(array $options): self
    {
        $allowed = ['disk', 'path', 'filename', 'format', 'delimiter', 'enclosure', 'escape', 'include_bom', 'include_headers', 'include_index', 'processing_mode', 'chunk_size', 'headings', 'fields', 'encoding', 'memory_limit', 'metadata', 'formula_policy'];
        if (array_diff(array_keys($options), $allowed) !== []) {
            throw new CSVConfigurationException('Unknown CSV export option.');
        }
        $format = $options['format'] ?? null;
        if ($format !== null && ! $format instanceof CSVExportFormatEnum) {
            throw new CSVConfigurationException('CSV export format is invalid.');
        }
        $base = $format === null ? $this->configuration : CSVConfiguration::fromFormat($format);
        $encoding = $options['encoding'] ?? $base->encoding;
        if ($encoding instanceof CSVEncodingEnum) {
            $encoding = $encoding->getPhpEncoding();
        }
        if ($encoding !== null && ! is_string($encoding)) {
            throw new CSVConfigurationException('CSV output encoding is invalid.');
        }
        $delimiter = $options['delimiter'] ?? $base->delimiter;
        if ($delimiter instanceof CSVDelimiterEnum) {
            $delimiter = $delimiter->getCharacter();
        }
        if (! is_string($delimiter)) {
            throw new CSVConfigurationException('CSV output delimiter is invalid.');
        }
        $processingMode = $options['processing_mode'] ?? $base->processingMode;
        if (! $processingMode instanceof CSVProcessingModeEnum) {
            throw new CSVConfigurationException('CSV output processing mode is invalid.');
        }
        $this->fields = self::fieldsOption($options);
        self::headingsOption($options);
        foreach (['disk', 'path', 'filename'] as $key) {
            if (array_key_exists($key, $options)) {
                self::stringOption($options, $key, '');
            }
        }
        self::arrayOption($options, 'metadata');
        $formulaPolicy = self::stringOption($options, 'formula_policy', 'escape');
        if (! in_array($formulaPolicy, ['escape', 'reject', 'allow'], true)) {
            throw new CSVConfigurationException('CSV formula policy is invalid.');
        }
        $this->configuration = new CSVConfiguration(
            delimiter: $delimiter,
            enclosure: self::stringOption($options, 'enclosure', $base->enclosure),
            escape: self::stringOption($options, 'escape', $base->escape),
            lineEnding: $base->lineEnding,
            includeBOM: self::boolOption($options, 'include_bom', ($options['encoding'] ?? null) instanceof CSVEncodingEnum && $options['encoding']->hasBOM() ? true : $base->includeBOM),
            includeHeaders: self::boolOption($options, 'include_headers', $base->includeHeaders),
            includeIndex: self::boolOption($options, 'include_index', $base->includeIndex),
            chunkSize: self::intOption($options, 'chunk_size', $base->chunkSize),
            processingMode: $processingMode,
            exportFormat: $format ?? $base->exportFormat,
            encoding: $encoding,
            memoryLimit: self::intOption($options, 'memory_limit', $base->memoryLimit),
            strictMode: $base->strictMode,
            skipEmptyRows: $base->skipEmptyRows,
            trimValues: $base->trimValues,
        );
        $this->options = ['path' => '', ...$options];

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
     * @return array<string,mixed>
     */
    private static function arrayOption(array $options, string $key): array
    {
        $value = $options[$key] ?? [];
        if (! is_array($value)) {
            throw new CSVConfigurationException("CSV option [{$key}] must be an array.");
        }
        $named = [];
        foreach ($value as $name => $item) {
            if (! is_string($name)) {
                throw new CSVConfigurationException("CSV option [{$key}] requires named fields.");
            }
            $named[$name] = $item;
        }

        return $named;
    }

    /** @param array<string,mixed> $options
     * @return list<string>
     */
    private static function headingsOption(array $options): array
    {
        $headings = $options['headings'] ?? [];
        if (! is_array($headings) || ! array_is_list($headings)) {
            throw new CSVConfigurationException('CSV headings must be a list.');
        }
        foreach ($headings as $heading) {
            if (! is_string($heading)) {
                throw new CSVConfigurationException('CSV headings must be strings.');
            }
        }

        return $headings;
    }

    /** @param array<string,mixed> $options
     * @return list<string|Closure>
     */
    private static function fieldsOption(array $options): array
    {
        $fields = $options['fields'] ?? [];
        if (! is_array($fields) || ! array_is_list($fields)) {
            throw new CSVConfigurationException('CSV fields must be a list.');
        }
        foreach ($fields as $field) {
            if (! is_string($field) && ! $field instanceof Closure) {
                throw new CSVConfigurationException('CSV fields must be names or synchronous callbacks.');
            }
        }

        return $fields;
    }

    /**
     * Set the storage disk for export output.
     *
     * Configures which Laravel storage disk to use for saving the exported CSV file.
     * Validates that the disk exists and is accessible before proceeding.
     *
     * @param  string  $disk  Storage disk name (local, s3, public, etc.)
     * @return self Returns this instance for method chaining
     *
     * @throws Exception If the specified disk is invalid or cannot be accessed
     */
    public function disk(string $disk): self
    {
        try {
            Storage::disk($disk);
        } catch (InvalidArgumentException) {
            throw CSVConfigurationException::invalidDisk($disk);
        }

        $this->options = array_merge($this->optionPayload(), ['disk' => $disk]);

        return $this;
    }

    /**
     * Set the directory path for export output.
     *
     * Configures the directory path within the storage disk where
     * the CSV file will be saved. Path should not include the filename.
     *
     * @param  string  $path  Directory path for the exported file
     * @return self Returns this instance for method chaining
     *
     * @throws Exception If path configuration cannot be updated
     */
    public function path(string $path): self
    {
        $this->options = array_merge($this->optionPayload(), ['path' => trim($path, '/')]);

        return $this;
    }

    /**
     * Set the filename for the exported CSV file.
     *
     * Configures the name of the output CSV file. Should include the .csv extension
     * if desired. The filename will be combined with the configured path.
     *
     * @param  string  $filename  Name of the exported CSV file (e.g., 'export.csv')
     * @return self Returns this instance for method chaining
     *
     * @throws Exception If filename configuration cannot be updated
     */
    public function filename(string $filename): self
    {
        $this->options = array_merge($this->optionPayload(), ['filename' => $filename]);

        return $this;
    }

    /**
     * Set the column headings for the CSV export.
     *
     * Defines the header row that will appear at the top of the CSV file.
     * Headers should correspond to the data fields being exported.
     *
     * @param  array<string>  $headings  Array of column header names
     * @return self Returns this instance for method chaining
     *
     * @throws Exception If headings configuration cannot be updated
     */
    public function headings(array $headings): self
    {
        $this->options = array_merge($this->optionPayload(), ['headings' => array_values($headings)]);
        $this->columnCount = count($headings);

        return $this;
    }

    /**
     * Set the field mappings for data extraction.
     *
     * Defines which fields to extract from each data row. Can be field names (strings)
     * or transformation functions (Closures) that receive the row data and return values.
     *
     * @param  array<string|Closure>  $fields  Array of field names or transformation functions
     * @return self Returns this instance for method chaining
     *
     * @throws Exception If field configuration cannot be updated
     */
    public function fields(array $fields): self
    {
        $this->fields = array_values($fields);
        $serializableFields = array_values(array_filter($fields, is_string(...)));
        $this->options = array_merge($this->optionPayload(), ['fields' => $serializableFields]);

        return $this;
    }

    /**
     * Enable chunked processing for large datasets.
     *
     * Configures the export to process data in chunks of the specified size
     * to manage memory usage when exporting large amounts of data.
     *
     * @param  int  $chunkSize  Number of rows to process in each chunk (default: 1000)
     * @return self Returns this instance for method chaining
     */
    public function chunked(int $chunkSize = 1000): self
    {
        $this->configuration = $this->configuration->withChunkSize($chunkSize);

        return $this;
    }

    /**
     * Export data from an array of associative arrays.
     *
     * Takes an array of data rows and exports them to CSV format.
     * Each row should be an associative array with consistent keys.
     * Supports both chunked and standard processing modes.
     *
     * @param  list<array<string, mixed>>  $data  Array of data rows to export
     * @return array<string, mixed> Export result with file information and statistics
     *
     * @throws RuntimeException If file operations fail or system resources are unavailable
     * @throws CSVConfigurationException If export configuration is invalid or incomplete
     */
    public function fromArray(array $data): array
    {
        $this->validateConfiguration();
        $this->openFile();

        try {
            $this->writeHeaders();

            if ($this->configuration->isChunked()) {
                $this->writeChunkedData($data);
            } else {
                $this->writeData($data);
            }

            $result = $this->saveFile();

            return $this->createResult($result);
        } catch (Throwable $e) {
            $this->closeFile();
            throw $e;
        } finally {
            $this->closeFile();
        }
    }

    /**
     * Export data directly from an Eloquent query builder.
     *
     * Efficiently exports database query results using chunked processing
     * to minimize memory usage for large datasets. Automatically handles
     * model-to-array conversion and relationship loading.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query  Eloquent query builder instance
     * @return array<string, mixed> Export result with file information and statistics
     *
     * @throws RuntimeException If file operations fail or database query fails
     * @throws CSVConfigurationException If export configuration is invalid or incomplete
     * @throws CSVMemoryException If memory limits are exceeded during processing
     */
    public function fromQuery(Builder $query): array
    {
        $this->validateConfiguration();
        $this->openFile();

        try {
            $this->writeHeaders();

            $chunkSize = $this->configuration->getEffectiveChunkSize();

            $query->chunk($chunkSize, function (Collection $rows) {
                $this->writeCollectionData($rows);
            });

            $result = $this->saveFile();

            return $this->createResult($result);
        } catch (Throwable $e) {
            $this->closeFile();
            throw $e;
        } finally {
            $this->closeFile();
        }
    }

    /**
     * Export data from a Laravel Collection.
     *
     * Converts the collection to array format and processes it for CSV export.
     * Supports collections of models, arrays, or other serializable data.
     *
     * @param  Collection<array-key, mixed>  $collection  Data to export
     * @return array<string, mixed> Export result with file information and statistics
     *
     * @throws RuntimeException If file operations fail or collection conversion fails
     * @throws CSVConfigurationException If export configuration is invalid or incomplete
     */
    public function fromCollection(Collection $collection): array
    {
        $this->validateConfiguration();
        $this->openFile();

        try {
            $this->writeHeaders();
            $this->writeCollectionData($collection);
            $result = $this->saveFile();

            return $this->createResult($result);
        } finally {
            $this->closeFile();
        }
    }

    /**
     * Stream export for very large datasets using a data provider callback.
     *
     * Allows for custom data streaming where the provider callback is responsible
     * for supplying data in chunks. Ideal for extremely large datasets that cannot
     * be loaded into memory at once.
     *
     * @param  Closure  $dataProvider  Callback that provides data chunks (callable $writer) => void
     * @return array Export result with file information and statistics
     *
     * @throws RuntimeException If file operations fail or data provider callback fails
     * @throws CSVConfigurationException If export configuration is invalid or incomplete
     */
    /** @return array<string,mixed> */
    public function stream(Closure $dataProvider): array
    {
        $this->validateConfiguration();
        $this->openFile();

        try {
            $this->writeHeaders();

            $dataProvider(function ($rows) {
                if ($rows instanceof Collection) {
                    $this->writeCollectionData($rows);
                } else {
                    /** @var array<array<string, mixed>> $data */
                    $data = (array) $rows;
                    $this->writeData($data);
                }
            });

            $result = $this->saveFile();

            return $this->createResult($result);
        } catch (Throwable $e) {
            $this->closeFile();
            throw $e;
        } finally {
            $this->closeFile();
        }
    }

    /**
     * Validate the export configuration before processing.
     *
     * Ensures all required configuration parameters are present and valid.
     * Sets up default options if none provided and configures memory management.
     *
     * @throws CSVConfigurationException If required configuration is missing or invalid
     */
    private function validateConfiguration(): void
    {

        if (empty($this->options['filename'])) {
            throw CSVConfigurationException::missingConfiguration('filename');
        }

        $this->startTime = microtime(true);
        $this->rowCount = 0;
        $this->columnCount = 0;
        $this->errors = [];
        $this->warnings = [];
        $this->headersWritten = false;
        $this->inferredFields = null;

        if ($this->configuration->memoryLimit !== null) {
            $this->memoryManager->setLimit($this->configuration->memoryLimit);
        }
    }

    /**
     * Open a temporary file handle for CSV writing.
     *
     * Creates a temporary stream containing canonical UTF-8 CSV data.
     *
     * @throws RuntimeException If temporary file creation fails
     */
    private function openFile(): void
    {
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            throw new RuntimeException('Unable to open temporary file for writing');
        }

        $this->handle = $handle;

    }

    /**
     * Close the file handle and clean up resources.
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
     * Write column headers to the CSV file.
     *
     * Writes the configured column headings as the first row of the CSV file
     * if headers are enabled in the configuration. Handles index column insertion.
     *
     * @param  list<string>  $fallbackHeadings
     */
    private function writeHeaders(array $fallbackHeadings = []): void
    {
        if (! $this->configuration->includeHeaders) {
            $this->headersWritten = true;

            return;
        }

        $headings = self::headingsOption($this->options);

        if ($headings === []) {
            $headings = $fallbackHeadings;
        }

        if ($headings === []) {
            return;
        }

        if ($this->configuration->includeIndex) {
            array_unshift($headings, '#');
        }

        if ($this->handle !== null) {
            $this->columnCount = count($headings);
            $headerValues = array_map(fn (string $heading): string => $this->applyFormulaPolicy($heading), $headings);
            fputcsv(
                $this->handle,
                $headerValues,
                $this->configuration->delimiter,
                $this->configuration->enclosure,
                $this->configuration->escape,
                $this->configuration->lineEnding,
            );
            $this->headersWritten = true;
        }
    }

    /**
     * Write multiple data rows to the CSV file.
     *
     * Processes and writes each row of data to the CSV file using
     * the configured field mappings and formatting options.
     *
     * @param  array<array<string, mixed>>  $data  Array of data rows to write
     */
    private function writeData(array $data): void
    {
        foreach ($data as $row) {
            $this->writeRow($row);
        }
    }

    /**
     * Write data in memory-efficient chunks.
     *
     * Divides the data into chunks and processes each chunk separately
     * to manage memory usage for large datasets. Includes memory monitoring
     * and automatic cleanup between chunks.
     *
     * @param  array<array<string, mixed>>  $data  Array of data rows to write in chunks
     *
     * @throws CSVMemoryException If memory limits are exceeded during chunk processing
     */
    private function writeChunkedData(array $data): void
    {
        $chunkSize = max(1, $this->configuration->getEffectiveChunkSize());
        $rowsInChunk = 0;

        foreach ($data as $row) {
            if ($rowsInChunk === 0 && ! $this->memoryManager->canAllocate($chunkSize * 1024)) {
                throw CSVMemoryException::memoryLimitExceeded(
                    $this->memoryManager->getCurrentUsage(),
                    $this->memoryManager->getLimit()
                );
            }

            $this->writeRow($row);
            $rowsInChunk++;

            if ($rowsInChunk === $chunkSize) {
                $this->memoryManager->cleanup();
                $rowsInChunk = 0;
            }
        }
    }

    /**
     * Write data from a Laravel Collection to the CSV file.
     *
     * Processes collection items, converting models to arrays as needed,
     * and writes each item as a CSV row using the configured formatting.
     *
     * @param  iterable<array-key, mixed>  $collection  Data items to write
     */
    private function writeCollectionData(iterable $collection): void
    {
        foreach ($collection as $item) {
            $rawRow = $item instanceof Model ? $item->toArray() : $item;
            if (! is_array($rawRow)) {
                throw new InvalidArgumentException('CSV collection items must be models or arrays.');
            }

            $row = [];
            foreach ($rawRow as $key => $value) {
                $row[(string) $key] = $value;
            }

            $this->writeRow($row);
        }
    }

    /**
     * Write a single data row to the CSV file.
     *
     * Processes a single row of data according to field mappings, applies
     * transformations, and writes it to the CSV file with proper formatting.
     * Handles row indexing if enabled.
     *
     * @param  array<string, mixed>  $row  Associative array of row data
     */
    private function writeRow(array $row): void
    {
        $fields = $this->fields;
        if ($fields === [] && isset($this->options['fields'])) {
            $fields = self::fieldsOption($this->options);
        }

        if (empty($fields)) {
            $fields = $this->inferredFields ??= array_keys($row);
        }

        if (! $this->headersWritten && $this->configuration->includeHeaders) {
            $headings = [];
            foreach ($fields as $field) {
                if (! is_string($field)) {
                    throw new CSVConfigurationException(
                        'Explicit headings are required when exporting closure-based fields.',
                    );
                }

                $headings[] = $field;
            }
            $this->writeHeaders($headings);
        }

        $this->rowCount++;
        $csvRow = [];

        if ($this->configuration->includeIndex) {
            $csvRow[] = $this->rowCount;
        }

        foreach ($fields as $field) {
            if ($field instanceof Closure) {
                $value = $field($row);
            } else {
                $value = Arr::get($row, $field);
            }

            if ($this->configuration->trimValues && is_string($value)) {
                $value = trim($value);
            }

            $csvRow[] = $this->sanitizeValue($value);
        }

        if ($this->columnCount === 0) {
            $this->columnCount = count($csvRow);
        } elseif ($this->configuration->strictMode && count($csvRow) !== $this->columnCount) {
            throw new CSVConfigurationException(
                "Export row {$this->rowCount} has ".count($csvRow)." columns; expected {$this->columnCount}.",
            );
        }

        if ($this->handle !== null) {
            fputcsv(
                $this->handle,
                $csvRow,
                $this->configuration->delimiter,
                $this->configuration->enclosure,
                $this->configuration->escape,
                $this->configuration->lineEnding,
            );
        }
    }

    /**
     * Sanitize and format a value for CSV output.
     *
     * Converts various data types to appropriate string representations for CSV.
     * Handles null values, booleans, arrays/objects, and character encoding.
     *
     * @param  mixed  $value  Value to sanitize and format
     * @return string Formatted string value suitable for CSV output
     *
     * @throws JsonException
     * @throws InvalidArgumentException
     */
    private function sanitizeValue(mixed $value): string
    {
        $text = match (true) {
            $value === null => '',
            is_bool($value) => $value ? '1' : '0',
            $value instanceof BackedEnum => (string) $value->value,
            $value instanceof DateTimeInterface => $value->format(DATE_ATOM),
            $value instanceof Stringable => (string) $value,
            is_array($value) || is_object($value) => json_encode($value, JSON_THROW_ON_ERROR),
            is_scalar($value) => (string) $value,
            default => throw new InvalidArgumentException('CSV values must be scalar, null, arrays, or objects.'),
        };

        return is_int($value) || is_float($value) ? $text : $this->applyFormulaPolicy($text);
    }

    private function applyFormulaPolicy(string $text): string
    {
        if (preg_match('/^[\x00-\x20]*[=+@-]/', $text) === 1) {
            $policy = $this->options['formula_policy'] ?? 'escape';
            if ($policy === 'reject') {
                throw new CSVConfigurationException('CSV formula-like values are rejected.');
            }
            if ($policy === 'escape') {
                return "'".$text;
            }
        }

        return $text;
    }

    /**
     * Build a stream encoded for the configured output.
     *
     * @return resource
     */
    private function buildOutputStream()
    {
        if (! is_resource($this->handle)) {
            throw new RuntimeException('CSV output stream is not open.');
        }

        rewind($this->handle);
        $output = fopen('php://temp', 'r+');
        if ($output === false) {
            throw new RuntimeException('Unable to open encoded CSV output stream.');
        }

        $encoding = $this->configuration->encoding ?? 'UTF-8';
        if ($this->configuration->includeBOM) {
            fwrite($output, $this->bomForEncoding($encoding));
        }

        $filterEncoding = match (strtoupper(str_replace('_', '-', $encoding))) {
            'UTF-16' => 'UTF-16LE',
            'UTF-32' => 'UTF-32BE',
            default => $encoding,
        };
        $filter = null;

        if (strcasecmp($filterEncoding, 'UTF-8') !== 0) {
            $filter = stream_filter_append(
                $output,
                "convert.iconv.UTF-8/{$filterEncoding}",
                STREAM_FILTER_WRITE
            );

            if ($filter === false) {
                fclose($output);
                throw new RuntimeException("Unable to encode CSV output as {$encoding}.");
            }
        }

        stream_copy_to_stream($this->handle, $output);
        if (is_resource($filter)) {
            stream_filter_remove($filter);
        }
        rewind($output);

        return $output;
    }

    /**
     * Save the CSV stream to the configured storage location.
     *
     * Writes the complete CSV content to the specified disk and path,
     * and attempts to generate a public URL if supported by the storage driver.
     *
     * @return array{path: string, storage_path: string, url: string|null, file_size: int}
     */
    private function saveFile(): array
    {
        $diskName = $this->resolveDiskName();
        $directory = trim(self::stringOption($this->options, 'path', 'exports'), '/');
        $fullPath = ($directory === '' ? '' : $directory.'/').self::stringOption($this->options, 'filename', 'export.csv');
        $diskInstance = Storage::disk($diskName);
        $output = $this->buildOutputStream();

        try {
            if (! $diskInstance->put($fullPath, $output)) {
                throw new RuntimeException("Unable to write CSV export to disk '{$diskName}'.");
            }
        } finally {
            fclose($output);
        }

        try {
            $resultPath = $diskInstance->path($fullPath);
        } catch (RuntimeException) {
            $resultPath = $fullPath;
        }

        $url = null;
        try {
            $url = $diskInstance->url($fullPath);
        } catch (RuntimeException) {
        }

        return [
            'path' => $resultPath,
            'storage_path' => $fullPath,
            'url' => $url,
            'file_size' => $diskInstance->size($fullPath),
        ];
    }

    /**
     * Create the final export result with comprehensive statistics and metadata.
     *
     * Compiles export statistics, file information, processing time,
     * and metadata into a structured result object that provides complete
     * information about the export operation.
     *
     * @param  array{path: string, storage_path: string, url: string|null, file_size: int}  $fileData
     * @return array<string, mixed> Complete export result with statistics and file information
     */
    private function createResult(array $fileData): array
    {
        $processingTime = microtime(true) - $this->startTime;
        $diskName = $this->resolveDiskName();
        $metadata = $this->options['metadata'] ?? null;
        $optionMetadata = is_array($metadata) ? $metadata : [];

        return array_merge($fileData, [
            'row_count' => $this->rowCount,
            'column_count' => $this->columnCount,
            'file_size' => $fileData['file_size'],
            'processing_time' => $processingTime,
            'disk' => $diskName,
            'metadata' => array_merge($optionMetadata, [
                'storage_path' => $fileData['storage_path'],
            ]),
            'errors' => $this->errors,
            'warnings' => $this->warnings,
        ]);
    }

    private function resolveDiskName(): string
    {
        $disk = $this->options['disk'] ?? null;

        return is_string($disk) && $disk !== '' ? $disk : 'local';
    }

    /**
     * Get a complete builder payload before replacing one option.
     *
     * @return array<string, mixed>
     */
    private function optionPayload(): array
    {
        return $this->options;
    }

    /**
     * Resolve BOM bytes for a target encoding.
     */
    private function bomForEncoding(string $encoding): string
    {
        return match (strtoupper(str_replace('_', '-', $encoding))) {
            'UTF-8', 'UTF8', 'UTF-8-BOM' => "\xEF\xBB\xBF",
            'UTF-16', 'UTF-16LE' => "\xFF\xFE",
            'UTF-16BE' => "\xFE\xFF",
            'UTF-32', 'UTF-32BE' => "\x00\x00\xFE\xFF",
            'UTF-32LE' => "\xFF\xFE\x00\x00",
            default => '',
        };
    }
}
