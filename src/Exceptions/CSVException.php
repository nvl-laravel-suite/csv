<?php

declare(strict_types=1);

namespace Nvl\Csv\Exceptions;

use Exception;
use Nvl\Csv\Enums\CSVResponseCode;
use Nvl\Support\Contracts\RespondableException;
use Nvl\Support\Exceptions\ExceptionResponse;
use Nvl\Support\Traits\InteractsWithPackageFailure;

/**
 * @api
 * Base exception for all CSV-related errors
 */
abstract class CSVException extends Exception implements RespondableException
{
    use InteractsWithPackageFailure;

    /** Resolve the declared safe failure for this native hierarchy. */
    protected function exceptionResponse(): ExceptionResponse
    {
        return match (static::class) {
            CSVConfigurationException::class => new ExceptionResponse('csv', CSVResponseCode::InvalidConfiguration, 500),
            CSVMemoryException::class => new ExceptionResponse('csv', CSVResponseCode::MemoryLimitExceeded, 500),
            CSVFileNotFoundException::class => new ExceptionResponse('csv', CSVResponseCode::FileNotFound, 404),
            CSVValidationException::class => new ExceptionResponse('csv', CSVResponseCode::InvalidCsvInput, 422),
            CSVParseException::class => new ExceptionResponse('csv', CSVResponseCode::CsvParseFailed, 422),
            default => new ExceptionResponse('csv', CSVResponseCode::OperationFailed),
        };
    }

    /**
     * Additional context data.
     *
     * @var array<string, mixed>
     */
    protected array $context = [];

    /**
     * Set exception context.
     *
     * @param  array<string, mixed>  $context  Context data
     * @return static Exception instance
     */
    public function withContext(array $context): static
    {
        $this->context = $context;

        return $this;
    }

    /**
     * Get exception context.
     *
     * @return array<string, mixed> Context data
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Get a specific context value.
     *
     * @param  string  $key  Context key
     * @param  mixed  $default  Default value when key is missing
     * @return mixed Context value or default
     */
    public function getContextValue(string $key, mixed $default = null): mixed
    {
        return $this->context[$key] ?? $default;
    }

    /**
     * Convert exception to array for logging.
     *
     * @return array<string, mixed> Exception details
     */
    public function toArray(): array
    {
        return [
            'exception' => static::class,
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
            'file' => $this->getFile(),
            'line' => $this->getLine(),
            'context' => $this->context,
        ];
    }
}
