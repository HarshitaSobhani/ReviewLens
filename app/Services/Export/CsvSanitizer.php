<?php

namespace App\Services\Export;

/** Neutralises spreadsheet formula injection in untrusted CSV cells. */
final class CsvSanitizer
{
    public static function cell(mixed $value): string|int|float
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }
        if (is_bool($value)) {
            return $value ? 'yes' : 'no';
        }
        $s = (string) ($value ?? '');
        // Leading whitespace/control chars can hide a formula trigger from the check.
        if (preg_match('/^[\s\x00-\x1F]*[=+\-@\t\r|%]/u', $s) === 1 && ! preg_match('/^-?\d+(\.\d+)?$/', $s)) {
            return "'".$s;
        }

        return $s;
    }

    public static function row(array $values): array
    {
        return array_map([self::class, 'cell'], $values);
    }
}
