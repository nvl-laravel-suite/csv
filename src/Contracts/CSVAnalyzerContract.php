<?php

declare(strict_types=1);

namespace Nvl\Csv\Contracts;

use DivisionByZeroError;
use Exception;
use Illuminate\Support\Facades\Storage;
use Nvl\Csv\Data\CSVAnalysisResultData;
use Nvl\Csv\Exceptions\CSVFileNotFoundException;
use RuntimeException;

/**
 * Defines the consumer-facing CSVAnalyzerService workflow.
 *
 * @api
 */
interface CSVAnalyzerContract
{
    /**
     * Perform comprehensive analysis of a CSV file from filesystem path.
     *
     * Conducts detailed analysis including file structure detection, encoding analysis,
     * data quality assessment, column type inference, and processing recommendations.
     * Returns comprehensive results suitable for optimizing import operations.
     *
     * @param  string  $filePath  Absolute path to the CSV file to analyze
     * @return CSVAnalysisResultData Complete analysis results with recommendations and statistics
     *
     * @throws CSVFileNotFoundException If the specified file does not exist or cannot be accessed
     * @throws RuntimeException If file operations fail or system resources are unavailable
     * @throws Exception If analysis operations encounter unexpected errors
     * @throws DivisionByZeroError If statistical calculations encounter division by zero
     */
    public function analyzeFile(string $filePath): CSVAnalysisResultData;

    /**
     * Analyze a CSV file from a Laravel storage disk.
     *
     * Uses Laravel's Storage facade to access and analyze files from configured
     * storage disks (local, s3, etc.). Provides the same comprehensive analysis
     * as analyzeFile but with storage abstraction.
     *
     * @param  string  $disk  Storage disk name (local, s3, public, etc.)
     * @param  string  $path  Path to the file on the specified storage disk
     * @return CSVAnalysisResultData Complete analysis results with recommendations and statistics
     *
     * @throws CSVFileNotFoundException If the file does not exist on the specified disk
     * @throws RuntimeException If file operations or disk access fails
     * @throws Exception If analysis operations encounter unexpected errors
     * @throws DivisionByZeroError If statistical calculations encounter division by zero
     */
    public function analyzeFromDisk(string $disk, string $path): CSVAnalysisResultData;

    /**
     * Perform quick analysis for basic file properties and structure.
     *
     * Provides lightweight analysis focusing on essential file characteristics
     * like encoding, delimiter, headers, and basic structure. Suitable for
     * initial file validation and configuration hints.
     *
     * @param  string  $filePath  Absolute path to the CSV file to analyze
     * @return array<string, mixed> Basic file properties including size, encoding, delimiter, and headers
     *
     * @throws CSVFileNotFoundException If the specified file does not exist
     * @throws RuntimeException If file operations fail
     */
    public function quickAnalyze(string $filePath): array;
}
