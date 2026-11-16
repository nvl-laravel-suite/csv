<?php

declare(strict_types=1);

namespace Nvl\Csv\Support;

use Nvl\Csv\Exceptions\CSVParseException;

/**
 * Reads bounded logical CSV records terminated by CR, LF or CRLF.
 *
 * Line breaks inside an enclosed field belong to the record. Each record is limited while it is
 * read, before the parser allocates its fields, and no global INI setting is changed.
 *
 * @internal
 */
final class CSVRecordReader
{
    public const int MaxRecordBytes = 8388608;

    private const int ReadBytes = 65536;

    private string $buffer = '';

    private int $position = 0;

    private bool $exhausted = false;

    /** @param resource $handle */
    public function __construct(
        private $handle,
        private readonly string $delimiter,
        private readonly string $enclosure,
        private readonly string $escape,
        private readonly int $maxRecordBytes = self::MaxRecordBytes,
    ) {}

    /**
     * Read and parse the next logical record.
     *
     * @return array<int, string|null>|false
     *
     * @throws CSVParseException When a logical record exceeds the byte bound
     */
    public function read(): array|false
    {
        $record = $this->nextRecord();

        return $record === null
            ? false
            : str_getcsv($record, $this->delimiter, $this->enclosure, $this->escape);
    }

    /** Bytes read from the stream but not yet returned as records. */
    public function bufferedBytes(): int
    {
        return strlen($this->buffer) - $this->position;
    }

    private function nextRecord(): ?string
    {
        $special = $this->enclosure.$this->delimiter."\r\n".$this->escape;
        $offset = $this->position;
        $fieldStart = $this->position;
        $quoted = false;

        while (true) {
            $length = strlen($this->buffer);
            while ($offset < $length) {
                $offset += strcspn($this->buffer, $special, $offset);
                if ($offset >= $length) {
                    break;
                }
                $character = $this->buffer[$offset];

                if ($quoted) {
                    if ($this->escape !== '' && $character === $this->escape && $character !== $this->enclosure) {
                        $offset += 2;
                    } elseif ($character !== $this->enclosure) {
                        $offset++;
                    } elseif ($offset + 1 >= $length && ! $this->exhausted) {
                        break;
                    } elseif (($this->buffer[$offset + 1] ?? '') === $this->enclosure) {
                        $offset += 2;
                    } else {
                        $quoted = false;
                        $offset++;
                    }

                    continue;
                }

                if ($character === $this->enclosure) {
                    $leading = $offset - $fieldStart;
                    $quoted = $leading === strspn($this->buffer, " \t\v\f", $fieldStart, $leading);
                    $offset++;

                    continue;
                }

                if ($character === $this->delimiter) {
                    $fieldStart = ++$offset;

                    continue;
                }

                if ($character !== "\r" && $character !== "\n") {
                    $offset++;

                    continue;
                }

                if ($character === "\r" && $offset + 1 >= $length && ! $this->exhausted) {
                    break;
                }

                $terminator = $character === "\r" && ($this->buffer[$offset + 1] ?? '') === "\n" ? 2 : 1;
                $this->assertWithinBound($offset - $this->position);
                $record = substr($this->buffer, $this->position, $offset - $this->position);
                $this->position = $offset + $terminator;

                return $record;
            }

            $this->assertWithinBound(min($offset, $length) - $this->position);

            if ($this->exhausted) {
                if ($this->position >= $length) {
                    return null;
                }
                $record = substr($this->buffer, $this->position);
                $this->buffer = '';
                $this->position = 0;

                return $record;
            }

            $consumed = $this->position;
            $this->fill();
            $offset -= $consumed;
            $fieldStart -= $consumed;
        }
    }

    private function assertWithinBound(int $recordBytes): void
    {
        if ($recordBytes > $this->maxRecordBytes) {
            throw CSVParseException::parsingFailed("logical record exceeds {$this->maxRecordBytes} bytes");
        }
    }

    /** Append the next stream block after dropping returned records from the buffer. */
    private function fill(): void
    {
        $this->buffer = substr($this->buffer, $this->position);
        $this->position = 0;
        $chunk = fread($this->handle, self::ReadBytes);
        if ($chunk === false || ($chunk === '' && feof($this->handle))) {
            $this->exhausted = true;

            return;
        }
        $this->buffer .= $chunk;
    }
}
