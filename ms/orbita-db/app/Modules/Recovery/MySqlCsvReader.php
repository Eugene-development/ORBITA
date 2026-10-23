<?php

declare(strict_types=1);

namespace App\Modules\Recovery;

use Generator;

/** Headerless MySQL CSV: comma delimiter, optional quotes, backslash escapes. */
final class MySqlCsvReader
{
    /** @return Generator<int, list<?string>> */
    public function rows(string $bytes, string $table): Generator
    {
        if (str_starts_with($bytes, "\xEF\xBB\xBF")) {
            $bytes = substr($bytes, 3);
        }
        if (! mb_check_encoding($bytes, 'UTF-8')) {
            throw new RecoveryException("{$table}: CSV is not valid UTF-8.");
        }
        $length = strlen($bytes);
        $offset = 0;
        $record = 0;
        while ($offset < $length) {
            $row = [];
            do {
                $quoted = $offset < $length && $bytes[$offset] === '"';
                $raw = '';
                if ($quoted) {
                    $offset++;
                    $closed = false;
                    while ($offset < $length) {
                        $char = $bytes[$offset++];
                        if ($char === '\\') {
                            if ($offset >= $length) {
                                throw new RecoveryException("{$table}: incomplete escape in record ".($record + 1).'.');
                            }
                            $raw .= $char.$bytes[$offset++];
                        } elseif ($char === '"') {
                            // Accept the standard doubled enclosure as well as MySQL \".
                            if ($offset < $length && $bytes[$offset] === '"') {
                                $raw .= '\\"';
                                $offset++;
                            } else {
                                $closed = true;
                                break;
                            }
                        } else {
                            $raw .= $char;
                        }
                    }
                    if (! $closed) {
                        throw new RecoveryException("{$table}: unclosed quote in record ".($record + 1).'.');
                    }
                } else {
                    while ($offset < $length && ! str_contains(",\r\n", $bytes[$offset])) {
                        $char = $bytes[$offset++];
                        if ($char === '"') {
                            throw new RecoveryException("{$table}: unexpected quote in record ".($record + 1).'.');
                        }
                        $raw .= $char;
                        if ($char === '\\') {
                            if ($offset >= $length) {
                                throw new RecoveryException("{$table}: incomplete escape in record ".($record + 1).'.');
                            }
                            $raw .= $bytes[$offset++];
                        }
                    }
                }
                $row[] = ! $quoted && $raw === '\\N' ? null : $this->decode($raw);
                $separator = $offset < $length ? $bytes[$offset++] : null;
                if ($separator !== null && ! str_contains(",\r\n", $separator)) {
                    throw new RecoveryException("{$table}: invalid enclosure in record ".($record + 1).'.');
                }
            } while ($separator === ',');
            if ($separator === "\r" && $offset < $length && $bytes[$offset] === "\n") {
                $offset++;
            }
            yield ++$record => $row;
        }
    }

    private function decode(string $raw): string
    {
        // Quoted \N is literal text; only the unquoted field is SQL NULL.
        if ($raw === '\\N') {
            return $raw;
        }
        return preg_replace_callback('/\\\\(.)/s', static fn (array $match): string => match ($match[1]) {
            '0' => "\0", 'b' => "\x08", 'n' => "\n", 'r' => "\r", 't' => "\t", 'Z' => "\x1A",
            '%', '_' => '\\'.$match[1],
            default => $match[1],
        }, $raw);
    }
}
