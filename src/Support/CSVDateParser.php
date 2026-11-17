<?php

declare(strict_types=1);

namespace Nvl\Csv\Support;

use Carbon\Carbon;
use DateTimeInterface;
use InvalidArgumentException;
use Stringable;

/**
 * Parses complete calendar dates strictly, with or without a declared format.
 *
 * Shared by CSVTypeEnum and DateTransformer so validation, casting and transformation agree.
 *
 * @internal
 */
final class CSVDateParser
{
    /**
     * Parse a value, rejecting relative text and calendar rollover such as 2026-02-31.
     *
     * @param  string|null  $format  PHP date format; unspecified fields are reset rather than taken from now
     *
     * @throws InvalidArgumentException When the value is not one complete, valid date
     */
    public static function parse(mixed $value, ?string $format = null): Carbon
    {
        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value);
        }

        if ($format !== null) {
            if (! is_scalar($value) && ! $value instanceof Stringable) {
                throw new InvalidArgumentException('Formatted date values must be strings or numbers.');
            }
            $strictFormat = str_contains($format, '!') || str_contains($format, '|') ? $format : '!'.$format;
            $date = Carbon::createFromFormat($strictFormat, (string) $value);
            if ($date === null) {
                throw new InvalidArgumentException('Date value does not match its declared format.');
            }
            self::assertNoParseErrors();
            if (preg_match('/(?<!\\\\)[Dl]/', $strictFormat) === 1) {
                self::assertCalendarDatePreserved($date, date_parse_from_format($strictFormat, (string) $value));
            }

            return $date;
        }

        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            throw new InvalidArgumentException('Date values must be strings, numbers, or DateTime instances.');
        }

        if (is_string($value) && Carbon::hasRelativeKeywords($value)) {
            throw new InvalidArgumentException('Date values must name a complete calendar date.');
        }

        $date = Carbon::parse($value);
        self::assertNoParseErrors();

        return $date;
    }

    /**
     * Reject a parsed date whose calendar day differs from the written one.
     *
     * PHP moves a date forward to a parsed weekday name (D or l), so a weekday that contradicts the
     * written day silently shifts it; such values are contradictory rather than valid.
     *
     * @param  array<string, mixed>  $written  Components from date_parse_from_format()
     */
    private static function assertCalendarDatePreserved(Carbon $date, array $written): void
    {
        $parsed = ['year' => $date->year, 'month' => $date->month, 'day' => $date->day];
        foreach ($parsed as $component => $parsedValue) {
            if (is_int($written[$component] ?? null) && $written[$component] !== $parsedValue) {
                throw new InvalidArgumentException('Date value names a weekday that contradicts its calendar date.');
            }
        }
    }

    /** Reject the warnings PHP reports for rolled-over or partially parsed dates. */
    private static function assertNoParseErrors(): void
    {
        $parseErrors = Carbon::getLastErrors();
        if ($parseErrors !== false && ($parseErrors['warning_count'] > 0 || $parseErrors['error_count'] > 0)) {
            throw new InvalidArgumentException('Date value contains invalid calendar components.');
        }
    }
}
