<?php

declare(strict_types=1);

namespace Nvl\Csv\Contracts;

use Closure;
use DivisionByZeroError;
use Exception;
use Illuminate\Bus\Batch;
use Nvl\Csv\Data\CSVImportOptionsData;
use Nvl\Csv\ValueObjects\CSVFieldMapping;
use RuntimeException;

/**
 * Defines the consumer-facing CSVAsyncProcessor workflow.
 *
 * @api
 */
interface CSVAsyncProcessorContract
{
    /**
     * Set the file path to process.
     *
     * @param  string  $filePath  CSV file path
     * @return self Processor instance
     */
    public function fromFile(string $filePath): self;

    /**
     * Set processing options.
     *
     * @param  CSVImportOptionsData  $options  Import options
     * @return self Processor instance
     */
    public function withOptions(CSVImportOptionsData $options): self;

    /**
     * Set the chunk size for batch processing.
     *
     * @param  int  $size  Number of rows per chunk (default: 1000)
     * @return self Processor instance
     */
    public function withChunkSize(int $size): self;

    /**
     * Add field mapping for data transformation.
     *
     * @param  string  $csvField  CSV field name
     * @param  string  $targetField  Target field name
     * @param  CSVFieldMapping|null  $mapping  Field mapping configuration
     * @return self Processor instance
     */
    public function mapField(string $csvField, string $targetField, ?CSVFieldMapping $mapping = null): self;

    /**
     * Set row processor callback for each processed row.
     *
     * @param  Closure  $processor  Row processor callback
     * @return self Processor instance
     */
    public function processRow(Closure $processor): self;

    /**
     * Set progress callback for tracking processing progress.
     *
     * @param  Closure  $callback  Function that receives the current Laravel batch
     * @return self Processor instance
     */
    public function onProgress(Closure $callback): self;

    /**
     * Set batch completion callback.
     *
     * @param  Closure  $callback  Function that receives (chunkNumber, processedRows, errors)
     * @return self Processor instance
     */
    public function onBatchComplete(Closure $callback): self;

    /**
     * Set completion callback for when entire file is processed.
     *
     * @param  Closure  $callback  Function that receives the completed Laravel batch
     * @return self Processor instance
     */
    public function onComplete(Closure $callback): self;

    /** Select a class-resolved tenant row handler by immutable alias. */
    public function usingHandler(string $alias, int $version = 1): self;

    /**
     * Process the CSV file asynchronously.
     *
     * @return Batch Laravel batch instance for monitoring progress
     *
     * @throws RuntimeException
     * @throws Exception
     * @throws DivisionByZeroError
     */
    public function processAsync(): Batch;

    /**
     * Process the CSV file asynchronously with real-time progress tracking.
     *
     * This method provides a higher-level interface with built-in progress tracking
     * and status updates. Returns a batch ID that can be used to monitor progress.
     *
     * @return string Batch ID for monitoring
     *
     * @throws RuntimeException
     * @throws Exception
     * @throws DivisionByZeroError
     */
    public function processAsyncWithTracking(): string;

    /**
     * Get the status of an async processing batch.
     *
     * @param  string  $batchId  The batch ID returned from processAsyncWithTracking()
     * @return array{
     *     id?: string,
     *     status: string,
     *     progress?: array{
     *         processed_jobs: int,
     *         pending_jobs: int,
     *         failed_jobs: int,
     *         total_jobs: int,
     *         progress_percentage: int
     *     },
     *     timing?: array<string, mixed>,
     *     metadata?: array<string, mixed>|null
     * }
     *
     * @throws RuntimeException
     */
    public function getBatchStatus(string $batchId): array;

    /**
     * Cancel an async processing batch.
     *
     * @param  string  $batchId  The batch ID to cancel
     * @return bool True if successfully cancelled
     *
     * @throws RuntimeException
     */
    public function cancelBatch(string $batchId): bool;
}
