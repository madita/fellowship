<?php

namespace App\Services\Migration;

use DateTime;

/**
 * Applies a stored field map to one row from a source database.
 *
 * A field map is: targetField => spec, where spec supports
 *  - source:    source column name to read from
 *  - transform: none|trim|html_decode|underscores_to_spaces|int|float|bool|json|date|time|datetime
 *  - format:    input format for the date/time/datetime transforms
 *               (e.g. "Ymd", "Hi"); defaults to letting PHP parse freely
 *  - default:   value used when the column is missing/null/empty
 *
 * Outputs are normalised: date => Y-m-d, time => H:i:s,
 * datetime => Y-m-d H:i:s. Unparseable values become null (then default).
 */
class RowMapper
{
    public const TRANSFORMS = ['none', 'trim', 'html_decode', 'underscores_to_spaces', 'int', 'float', 'bool', 'json', 'date', 'time', 'datetime'];

    /**
     * @param array<string,array<string,mixed>> $fieldMap
     */
    public function __construct(private array $fieldMap)
    {
    }

    /**
     * @param array<string,mixed> $row source row (column => value)
     * @return array<string,mixed> target field => mapped value
     */
    public function map(array $row): array
    {
        $mapped = [];

        foreach ($this->fieldMap as $targetField => $spec) {
            $mapped[$targetField] = $this->mapField($spec ?? [], $row);
        }

        return $mapped;
    }

    /**
     * @param array<string,mixed> $spec
     * @param array<string,mixed> $row
     */
    private function mapField(array $spec, array $row): mixed
    {
        $value = null;

        $sourceColumn = $spec['source'] ?? null;
        if ($sourceColumn !== null && $sourceColumn !== '') {
            $value = $row[$sourceColumn] ?? null;
            $value = $this->transform($value, $spec['transform'] ?? 'none', $spec['format'] ?? null);
        }

        if (($value === null || $value === '') && array_key_exists('default', $spec)) {
            return $spec['default'];
        }

        return $value;
    }

    private function transform(mixed $value, string $transform, ?string $format): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($transform) {
            'trim' => trim((string) $value),
            'html_decode' => html_entity_decode((string) $value),
            'underscores_to_spaces' => str_replace('_', ' ', (string) $value),
            'int' => is_numeric($value) ? (int) $value : null,
            'float' => is_numeric($value) ? (float) $value : null,
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            'json' => is_string($value) ? json_decode($value, true) : $value,
            'date' => $this->parseDate($value, $format)?->format('Y-m-d'),
            'time' => $this->parseDate($value, $format)?->format('H:i:s'),
            'datetime' => $this->parseDate($value, $format)?->format('Y-m-d H:i:s'),
            default => $value,
        };
    }

    private function parseDate(mixed $value, ?string $format): ?DateTime
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if ($format) {
            $parsed = DateTime::createFromFormat($format, $value);

            return $parsed === false ? null : $parsed;
        }

        try {
            return new DateTime($value);
        } catch (\Exception $e) {
            return null;
        }
    }
}
