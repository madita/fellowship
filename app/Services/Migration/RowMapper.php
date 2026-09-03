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
 *  - template:  string combining several columns, e.g. "{topic|fold}/{id}.jpg";
 *               placeholders are {column}, {column|slug} (URL slug) and
 *               {column|fold} (fold umlauts/specials for file system names).
 *               Takes precedence over source; null when any column is empty.
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

        $template = $spec['template'] ?? null;
        $sourceColumn = $spec['source'] ?? null;

        if ($template !== null && $template !== '') {
            $value = $this->applyTemplate($template, $row);
        } elseif ($sourceColumn !== null && $sourceColumn !== '') {
            $value = $row[$sourceColumn] ?? null;
            $value = $this->transform($value, $spec['transform'] ?? 'none', $spec['format'] ?? null);
        }

        if (($value === null || $value === '') && array_key_exists('default', $spec)) {
            return $spec['default'];
        }

        return $value;
    }

    /**
     * Substitute {column} / {column|slug} / {column|fold} placeholders with
     * row values. Returns null when any referenced column is missing/empty,
     * so the field can fall back to its default.
     */
    private function applyTemplate(string $template, array $row): ?string
    {
        $missing = false;

        $result = preg_replace_callback(
            '/\{([A-Za-z0-9_]+)(?:\|(slug|fold))?\}/',
            function ($match) use ($row, &$missing) {
                $value = $row[$match[1]] ?? null;
                if ($value === null || $value === '') {
                    $missing = true;

                    return '';
                }

                return match ($match[2] ?? '') {
                    'slug' => \Illuminate\Support\Str::slug((string) $value),
                    'fold' => self::foldForFilesystem((string) $value),
                    default => (string) $value,
                };
            },
            $template
        );

        return $missing ? null : $result;
    }

    /**
     * Fold umlauts/accents and path-hostile characters the way legacy file
     * archives commonly name their folders (ä→ae, / → _, …).
     */
    public static function foldForFilesystem(string $value): string
    {
        $value = strtr($value, [
            'ä' => 'ae', 'Ä' => 'Ae',
            'ö' => 'oe', 'Ö' => 'Oe',
            'ü' => 'ue', 'Ü' => 'Ue',
            'ß' => 'ss',
            'é' => 'e', 'è' => 'e', 'ê' => 'e',
        ]);

        return strtr($value, [
            '/' => '_', '\\' => '_', ':' => '_', '?' => '_',
            '&' => '_', '(' => '_', ')' => '_',
        ]);
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
