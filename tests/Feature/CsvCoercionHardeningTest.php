<?php

declare(strict_types=1);

use Nvl\Csv\Enums\CSVTypeEnum;
use Nvl\Csv\Filters\CSVFilter;
use Nvl\Csv\Transformers\DateTransformer;
use Nvl\Csv\Transformers\NumericTransformer;
use Nvl\Csv\Validators\CSVFieldValidator;

it('rejects relative, partial and time-only expressions as date cells', function (string $value): void {
    expect(CSVTypeEnum::DATE->validate($value))->toBeFalse()
        ->and(CSVTypeEnum::DATETIME->validate($value))->toBeFalse()
        ->and(fn () => CSVTypeEnum::DATE->cast($value))->toThrow(InvalidArgumentException::class)
        ->and((new DateTransformer)->default('fallback')->transform($value))->toBe('fallback');
})->with(['z', 'sat', 'now', 'tomorrow', '2024', '1230', '12:30', 'noon', '+1 day', 'Jan 15', '1.5']);

it('keeps accepting complete calendar dates', function (string $value, string $date): void {
    expect(CSVTypeEnum::DATE->validate($value))->toBeTrue()
        ->and(CSVTypeEnum::DATE->cast($value))->toBe($date)
        ->and((new DateTransformer)->transform($value))->toBe($date);
})->with([
    ['2024-01-15', '2024-01-15'],
    ['2024-01-15 10:00:00', '2024-01-15'],
    ['2024-01-15T10:00:00Z', '2024-01-15'],
    ['15.01.2024', '2024-01-15'],
    ['Jan 15 2024', '2024-01-15'],
    ['20240115', '2024-01-15'],
]);

it('rejects float cells that overflow to a non-finite number', function (): void {
    expect(CSVTypeEnum::FLOAT->validate('1e999'))->toBeFalse()
        ->and(CSVTypeEnum::NULLABLE_FLOAT->validate('-1e999'))->toBeFalse()
        ->and(fn () => CSVTypeEnum::FLOAT->cast('1e999'))->toThrow(InvalidArgumentException::class)
        ->and(CSVTypeEnum::FLOAT->cast('1e3'))->toBe(1000.0);
});

it('reads decimal commas instead of dropping the separator', function (string $value, float $expected): void {
    expect((new NumericTransformer)->transform($value))->toBe($expected);
})->with([
    ['1.234,56', 1234.56],
    ['1,5', 1.5],
    ['€ 12,50', 12.5],
    ['1 234,56', 1234.56],
    ['1,234.56', 1234.56],
    ['1,234', 1234.0],
    ['1,234,567', 1234567.0],
    ['$1,234.50', 1234.5],
]);

it('rejects line breaks in the email and phone field validators', function (): void {
    expect(CSVFieldValidator::email()->validate("person@example.com\n"))->toBeFalse()
        ->and(CSVFieldValidator::email()->validate('person@example.com'))->toBeTrue()
        ->and(CSVFieldValidator::phone()->validate("+359 888\n123 456"))->toBeFalse()
        ->and(CSVFieldValidator::phone()->validate("+359 888 123 456\n"))->toBeFalse()
        ->and(CSVFieldValidator::phone()->validate('+359 (888) 123-456'))->toBeTrue();
});

it('does not treat a zero cell as empty in field filters', function (): void {
    expect(CSVFilter::field('quantity')->isNotEmpty()->passes(['quantity' => '0']))->toBeTrue()
        ->and(CSVFilter::field('quantity')->isEmpty()->passes(['quantity' => '0']))->toBeFalse()
        ->and(CSVFilter::field('quantity')->isEmpty()->passes(['quantity' => 0]))->toBeFalse()
        ->and(CSVFilter::field('quantity')->isEmpty()->passes(['quantity' => '']))->toBeTrue()
        ->and(CSVFilter::field('quantity')->isEmpty()->passes([]))->toBeTrue()
        ->and(CSVFilter::field('quantity')->isEmpty()->passes(['quantity' => []]))->toBeTrue();
});
