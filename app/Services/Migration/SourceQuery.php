<?php

namespace App\Services\Migration;

use App\Models\MigrationMapping;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Builds the source query for a mapping, including the optional relations
 * stored in the mapping's options:
 *
 *  options.joins  = [{table, first, operator?, second, type?(left|inner)}, ...]
 *  options.wheres = [{column, operator?, value?, compare?}, ...]
 *                   compare "column" treats value as another column
 *                   (e.g. post_id != topic_first_post_id); default is a
 *                   plain value comparison.
 *  options.order_by = {column, direction?(asc|desc)} — import order, for
 *                   targets where parents must exist before children.
 *
 * Joined tables are selected with table.* so their columns become available
 * to the field map under their plain column names. When two tables share a
 * column name, the column of the last joined table wins — MediaWiki-style
 * schemas (page_*, rev_*, old_*) never collide.
 */
class SourceQuery
{
    public const JOIN_TYPES = ['left', 'inner'];

    public const OPERATORS = ['=', '!=', '<', '>', '<=', '>=', 'like'];

    /** Only plain (optionally table-qualified) identifiers are accepted. */
    public const IDENTIFIER_PATTERN = '/^[A-Za-z0-9_]+(\.[A-Za-z0-9_]+)?$/';

    public static function build(MigrationMapping $mapping): Builder
    {
        $connection = $mapping->source->connectionName();

        $query = DB::connection($connection)
            ->table($mapping->source_table)
            ->select("{$mapping->source_table}.*");

        foreach (self::joins($mapping) as $join) {
            $method = ($join['type'] ?? 'left') === 'inner' ? 'join' : 'leftJoin';
            $query->{$method}($join['table'], $join['first'], $join['operator'] ?? '=', $join['second']);
            $query->addSelect("{$join['table']}.*");
        }

        foreach (self::wheres($mapping) as $where) {
            if (($where['compare'] ?? 'value') === 'column') {
                $other = (string) ($where['value'] ?? '');
                if (!preg_match(self::IDENTIFIER_PATTERN, $other)) {
                    throw new \InvalidArgumentException("Filter compares against \"{$other}\", which is not a column name");
                }
                $query->whereColumn($where['column'], $where['operator'] ?? '=', $other);
            } else {
                $query->where($where['column'], $where['operator'] ?? '=', $where['value'] ?? null);
            }
        }

        $orderBy = $mapping->options['order_by'] ?? null;
        if (is_array($orderBy) && !empty($orderBy['column']) && preg_match(self::IDENTIFIER_PATTERN, $orderBy['column'])) {
            $query->orderBy($orderBy['column'], strtolower($orderBy['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc');
        }

        return $query;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function joins(MigrationMapping $mapping): array
    {
        return array_values(array_filter(
            $mapping->options['joins'] ?? [],
            fn ($join) => is_array($join) && !empty($join['table']) && !empty($join['first']) && !empty($join['second'])
        ));
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function wheres(MigrationMapping $mapping): array
    {
        return array_values(array_filter(
            $mapping->options['wheres'] ?? [],
            fn ($where) => is_array($where) && !empty($where['column'])
        ));
    }

    /**
     * All source tables the mapping reads from (base + joins).
     *
     * @return string[]
     */
    public static function tables(MigrationMapping $mapping): array
    {
        return array_values(array_unique(array_merge(
            [$mapping->source_table],
            array_column(self::joins($mapping), 'table')
        )));
    }
}
