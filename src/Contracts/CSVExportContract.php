<?php

declare(strict_types=1);

namespace Nvl\Csv\Contracts;

use Closure;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Nvl\Csv\Data\CSVExportOptionsData;
use Nvl\Csv\Exceptions\CSVConfigurationException;
use Nvl\Csv\Exceptions\CSVMemoryException;
use Nvl\Csv\ValueObjects\CSVConfiguration;
use Nvl\Csv\ValueObjects\CSVExportResult;
use RuntimeException;

/**
 * Defines the consumer-facing CSVExport workflow.
 *
 * @api
 */
interface CSVExportContract
{
    /**
     * Set the CSV export configuration.
     *
     * Replaces the current configuration with the provided one.
     * Used to customize formatting, delimiters, encoding, and processing options.
     *
     * @param  CSVConfiguration  $configuration  New configuration settings
     * @return self Returns this instance for method chaining
     */
    public function configure(CSVConfiguration $configuration): self;

    /**
     * Set export options from a structured DTO.
     *
     * Applies export-specific options including output path, filename,
     * column headings, and field mappings from a data transfer object.
     *
     * @param  CSVExportOptionsData  $options  Export options including path, filename, and field configuration
     * @return self Returns this instance for method chaining
     */
    public function withOptions(CSVExportOptionsData $options): self;

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
    public function disk(string $disk): self;

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
    public function path(string $path): self;

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
    public function filename(string $filename): self;

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
    public function headings(array $headings): self;

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
    public function fields(array $fields): self;

    /**
     * Enable chunked processing for large datasets.
     *
     * Configures the export to process data in chunks of the specified size
     * to manage memory usage when exporting large amounts of data.
     *
     * @param  int  $chunkSize  Number of rows to process in each chunk (default: 1000)
     * @return self Returns this instance for method chaining
     */
    public function chunked(int $chunkSize = 1000): self;

    /**
     * Export data from an array of associative arrays.
     *
     * Takes an array of data rows and exports them to CSV format.
     * Each row should be an associative array with consistent keys.
     * Supports both chunked and standard processing modes.
     *
     * @param  array<array<string, mixed>>  $data  Array of data rows to export
     * @return CSVExportResult Export result with file information and statistics
     *
     * @throws RuntimeException If file operations fail or system resources are unavailable
     * @throws CSVConfigurationException If export configuration is invalid or incomplete
     */
    public function fromArray(array $data): CSVExportResult;

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
     * @return CSVExportResult Export result with file information and statistics
     *
     * @throws RuntimeException If file operations fail or database query fails
     * @throws CSVConfigurationException If export configuration is invalid or incomplete
     * @throws CSVMemoryException If memory limits are exceeded during processing
     */
    public function fromQuery(Builder $query): CSVExportResult;

    /**
     * Export data from a Laravel Collection.
     *
     * Converts the collection to array format and processes it for CSV export.
     * Supports collections of models, arrays, or other serializable data.
     *
     * @param  Collection<array-key, mixed>  $collection  Data to export
     * @return CSVExportResult Export result with file information and statistics
     *
     * @throws RuntimeException If file operations fail or collection conversion fails
     * @throws CSVConfigurationException If export configuration is invalid or incomplete
     */
    public function fromCollection(Collection $collection): CSVExportResult;

    /**
     * Stream export for very large datasets using a data provider callback.
     *
     * Allows for custom data streaming where the provider callback is responsible
     * for supplying data in chunks. Ideal for extremely large datasets that cannot
     * be loaded into memory at once.
     *
     * @param  Closure  $dataProvider  Callback that provides data chunks (callable $writer) => void
     * @return CSVExportResult Export result with file information and statistics
     *
     * @throws RuntimeException If file operations fail or data provider callback fails
     * @throws CSVConfigurationException If export configuration is invalid or incomplete
     */
    public function stream(Closure $dataProvider): CSVExportResult;
}
