<?php

namespace App\Http\Controllers\DataTable;

use App\Http\Controllers\Controller;
use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Base class of the admin data tables (server-side paging, sorting, search).
 *
 * Query parameters:
 *  - page, per_page (legacy: itemsPerPage / itemsLength), clamped to $perPageOptions
 *  - sort_by + sort_dir, only sortable header keys; default $defaultSort
 *  - search: free text OR-ed across getSearchableColumns() (+ exact id when numeric)
 *  - column + operator + value: advanced filter, AND-ed with search
 *  - toggle filters are read by the concrete builder() (e.g. exclude_wiki)
 */
abstract class DataTableController extends Controller
{
    /** Escape character used in LIKE patterns (portable across MySQL/SQLite). */
    protected const LIKE_ESCAPE = '!';

    /**
     * If an entity is allowed to be created.
     *
     * @var bool
     */
    protected $allowCreation = true;

    /**
     * Allow deletion.
     *
     * @var bool
     */
    protected $allowDeletion = true;

    /* Does Edit Form for model exist? */
    protected $hasForm = false;

    /**
     * The entity builder (template only — queries are built from a fresh
     * builder() call so a reused controller instance never accumulates wheres).
     *
     * @var Builder
     */
    protected $builder;

    /**
     * Default sort as [column, direction] when no valid sort_by is requested.
     */
    protected array $defaultSort = ['id', 'desc'];

    /**
     * Page sizes a client may ask for; anything else clamps to the nearest.
     */
    protected array $perPageOptions = [10, 25, 50, 100];

    protected int $defaultPerPage = 10;

    /**
     * Column metadata per table name (Schema::getColumns), cached per instance.
     *
     * @var array<string, array<string, array>>
     */
    private array $schemaColumns = [];

    /**
     * Resolved header types keyed by column, cached per instance.
     */
    private ?array $resolvedColumnTypes = null;

    /**
     * Create the controller, check builder method and assign
     * to the builder property.
     *
     * @return void
     */
    public function __construct()
    {
        if ( ! method_exists($this, 'builder')) {
            throw new Exception('No entity builder method defined.');
        }

        if ( ! ($this->builder = $this->builder()) instanceof Builder) {
            throw new Exception('Entity builder not instance of Builder.');
        }
    }

    /**
     * Get the columns that are allowed to be displayed.
     *
     * Defaults to the table's columns minus hidden ones; translatable models
     * get their translated attributes right after the primary key.
     *
     * @return array
     */
    public function getDisplayableColumns()
    {
        $model   = $this->model();
        $columns = array_values(array_diff($this->getDatabaseColumnNames(), $model->getHidden()));

        $translated = array_values(array_diff($this->getTranslatedColumns(), $model->getHidden(), $columns));

        if ($translated) {
            $keyPosition = array_search($model->getKeyName(), $columns, true);
            array_splice($columns, $keyPosition === false ? 0 : $keyPosition + 1, 0, $translated);
        }

        return $columns;
    }

    /**
     * Get the columns that are allowed to be updated.
     *
     * @return array
     */
    public function getUpdatableColumns()
    {
        return array_intersect($this->getDatabaseColumnNames(), $this->getDisplayableColumns());
    }

    public function getCustomColumnsNames()
    {
        return [];
    }

    /**
     * Per-column type overrides: [column => id|text|longtext|boolean|number|date|datetime|json|image].
     */
    public function getColumnTypes(): array
    {
        return [];
    }

    /**
     * Columns the free-text `search` looks at: displayable text columns of the
     * table plus displayable translated attributes. Override to widen/narrow.
     */
    public function getSearchableColumns(): array
    {
        $types = $this->resolveColumnTypes();

        return array_values(array_filter(
            $this->getDisplayableColumns(),
            fn ($column) => $this->isQueryableColumn($column)
                && in_array($types[$column] ?? null, ['text', 'longtext'], true)
        ));
    }

    /**
     * Columns that can be sorted: real table columns (except json) and
     * translated attributes that are displayed.
     */
    public function getSortableColumns(): array
    {
        $types = $this->resolveColumnTypes();

        return array_values(array_filter(
            $this->getDisplayableColumns(),
            fn ($column) => $this->isTranslatedColumn($column)
                || ($this->isDatabaseColumn($column) && ($types[$column] ?? null) !== 'json')
        ));
    }

    public function getHeaders()
    {
        $columnNames = $this->getCustomColumnsNames();
        $types       = $this->resolveColumnTypes();
        $sortable    = $this->getSortableColumns();

        return collect($this->getDisplayableColumns())->values()->map(function ($column) use ($columnNames, $types, $sortable) {
            $title = $columnNames[$column] ?? $this->humanizeColumn($column);
            $type  = $types[$column] ?? 'text';

            return [
                'key'      => $column,
                'title'    => $title,
                'sortable' => in_array($column, $sortable, true),
                'type'     => $type,
                'align'    => $this->alignFor($type),
                // Backward compatibility with the previous header shape.
                'text'  => $title,
                'value' => $column,
            ];
        })->push([
            'key'      => 'actions',
            'title'    => 'Actions',
            'sortable' => false,
            'align'    => 'end',
            'text'     => 'Actions',
            'value'    => 'actions',
        ]);
    }

    /**
     * Get the paginated records to be used for output.
     *
     * @return LengthAwarePaginator
     */
    public function getRecords(Request $request)
    {
        $builder = $this->newQuery();

        if ($this->isTranslatable()) {
            // Avoid one translations query per row when serialising.
            $builder->with('translations');
        }

        $term = $this->resolveSearchTerm($request);
        if ($term !== null) {
            $this->applyFreeTextSearch($builder, $term);
        }

        if ($this->hasSearchQuery($request)) {
            $builder = $this->buildSearch($builder, $request);
        }

        [$sortBy, $sortDir] = $this->resolveSort($request);
        $this->applySort($builder, $sortBy, $sortDir);

        $perPage = $this->resolvePerPage($request);
        $page    = $this->resolvePage($request);

        try {
            $records = $builder->paginate($perPage, ['*'], 'page', $page)->withQueryString();
        } catch (QueryException $e) {
            report($e);

            $records = new LengthAwarePaginator([], 0, $perPage, $page, [
                'path'     => $request->url(),
                'pageName' => 'page',
            ]);
        }

        // if model has appended attributes and append attributes not in displayable columns... forget them
        $forget = array_values(array_diff($this->getAppends(), $this->getDisplayableColumns()));
        if ($forget) {
            $records->getCollection()->each->makeHidden($forget);
        }

        return $records;
    }

    /**
     * Show a list of entities.
     */
    public function index(Request $request): JsonResponse
    {
        [$sortBy, $sortDir] = $this->resolveSort($request);

        return response()->json([
            'data' => [
                'table'            => $this->model()->getTable(),
                'headers'          => $this->getHeaders(),
                'records'          => $this->getRecords($request),
                'updatable'        => array_values($this->getUpdatableColumns()),
                'displayable'      => array_values($this->getDisplayableColumns()),
                'searchable'       => $this->resolveSearchableColumns(),
                'sort'             => ['by' => $sortBy, 'dir' => $sortDir],
                'per_page_options' => array_values($this->perPageOptions),
                'column_map'       => $this->getCustomColumnsNames(),
                'column_fields'    => $this->getCustomInputFields(),
                'json_fields'      => $this->getCustomJsonFields(),
                'filter_fields'    => $this->getFilterFields(),
                'taxonomy_fields'  => $this->getTaxonomyFields(),
                'toggle_filters'   => $this->getToggleFilters(),
                'relations'        => $this->getRelations(),
                'allow'            => [
                    'hasForm'  => $this->hasForm,
                    'creation' => $this->allowCreation,
                    'deletion' => $this->allowDeletion,
                ],
            ],
        ]);
    }

    /**
     * Related records shown in the edit drawer, e.g. a user's event profiles.
     * Each entry has key, title, icon and an endpoint with an {id} placeholder
     * that returns { data: { columns: [{ key, title, type }], rows: [{ id, url,
     * values: { key: value }, details: [{ label, value }] }] } }.
     */
    public function getRelations(): array
    {
        return [];
    }

    public function show($id, Request $request): JsonResponse
    {
        $data = $this->newQuery()->find($id);

        return response()->json(
            $data
        );
    }

    /**
     * Create an entity.
     *
     *
     * @return Response|void
     */
    public function store(Request $request)
    {
        if ( ! $this->allowCreation) {
            return;
        }

        $this->newQuery()->create($request->only($this->getUpdatableColumns()));
    }

    /**
     * Update an entity.
     *
     * @param  int  $id
     * @return Response
     */
    public function update($id, Request $request)
    {
        return $this->newQuery()->findOrFail($id)->update($request->only($this->getUpdatableColumns()));
    }

    /**
     * Delete one or more entities: DELETE /api/datatable/{table}/{id,id,...}.
     *
     * Records are deleted one by one so model events run (translations,
     * soft deletes, cache busting, media, role pivots ...).
     *
     * @param  string|int  $ids
     * @return JsonResponse
     */
    public function destroy($ids, Request $request)
    {
        if ( ! $this->allowDeletion) {
            return response()->json([
                'message' => 'Deleting records is not allowed for this table.',
                'deleted' => 0,
            ], 403);
        }

        $keys = $this->parseIds($ids);

        if ($keys === []) {
            return response()->json([
                'message' => 'No valid ids given.',
                'deleted' => 0,
            ], 422);
        }

        $deleted = DB::transaction(function () use ($keys) {
            $count = 0;

            foreach ($this->newQuery()->whereKey($keys)->get() as $record) {
                if ($record->delete() !== false) {
                    $count++;
                }
            }

            return $count;
        });

        return response()->json([
            'message' => $deleted === 1 ? '1 record deleted.' : "{$deleted} records deleted.",
            'deleted' => $deleted,
        ]);
    }

    public function getCustomInputFields()
    {
        return [];
    }

    public function getCustomJsonFields()
    {
        return [];
    }

    public function getFilterFields()
    {
        return [];
    }

    public function getTaxonomyFields()
    {
        return [];
    }

    public function getToggleFilters()
    {
        return [];
    }

    public function getAppends()
    {
        return [];
    }

    public function getCategories($taxonomy)
    {
        $this->builder->getModel()->getCategories($taxonomy);
    }

    /**
     * A fresh query from the concrete builder() for every operation.
     */
    protected function newQuery(): Builder
    {
        $builder = $this->builder();

        if ( ! $builder instanceof Builder) {
            throw new Exception('Entity builder not instance of Builder.');
        }

        return $builder;
    }

    protected function model(): Model
    {
        return $this->builder->getModel();
    }

    /**
     * Get the database column names for the entity.
     */
    protected function getDatabaseColumnNames(): array
    {
        return array_merge(array_keys($this->getTableColumns($this->model()->getTable())), $this->getAppends());
    }

    /**
     * Column metadata (name, type_name, type, ...) of a table on the model's connection.
     */
    protected function getTableColumns(string $table): array
    {
        if ( ! array_key_exists($table, $this->schemaColumns)) {
            $columns = [];

            foreach ($this->model()->getConnection()->getSchemaBuilder()->getColumns($table) as $column) {
                $columns[$column['name']] = $column;
            }

            $this->schemaColumns[$table] = $columns;
        }

        return $this->schemaColumns[$table];
    }

    protected function isTranslatable(): bool
    {
        $model = $this->model();

        return $model instanceof TranslatableContract
            && property_exists($model, 'translatedAttributes')
            && is_array($model->translatedAttributes);
    }

    /**
     * The model's Astrotomic translated attributes (empty for plain models).
     */
    protected function getTranslatedColumns(): array
    {
        return $this->isTranslatable() ? array_values($this->model()->translatedAttributes) : [];
    }

    protected function getTranslationTable(): ?string
    {
        return $this->isTranslatable() ? $this->model()->translations()->getRelated()->getTable() : null;
    }

    protected function isDatabaseColumn(string $column): bool
    {
        return array_key_exists($column, $this->getTableColumns($this->model()->getTable()));
    }

    protected function isTranslatedColumn(string $column): bool
    {
        return ! $this->isDatabaseColumn($column) && in_array($column, $this->getTranslatedColumns(), true);
    }

    /**
     * A column that can safely be used in a where/order clause.
     */
    protected function isQueryableColumn(string $column): bool
    {
        return $this->isDatabaseColumn($column) || $this->isTranslatedColumn($column);
    }

    /**
     * Type of every displayable column: getColumnTypes() override, else
     * detected from casts / DB column type.
     */
    protected function resolveColumnTypes(): array
    {
        if ($this->resolvedColumnTypes === null) {
            $overrides = $this->getColumnTypes();
            $types     = [];

            foreach ($this->getDisplayableColumns() as $column) {
                $types[$column] = $overrides[$column] ?? $this->detectColumnType($column);
            }

            $this->resolvedColumnTypes = $types;
        }

        return $this->resolvedColumnTypes;
    }

    protected function detectColumnType(string $column): string
    {
        $model = $this->model();

        if ($column === $model->getKeyName()) {
            return 'id';
        }

        if ($this->isTranslatedColumn($column)) {
            $meta = $this->getTableColumns($this->getTranslationTable())[$column] ?? null;

            return $meta && $this->mapDatabaseType($meta) === 'longtext' ? 'longtext' : 'text';
        }

        if ($type = $this->typeFromCast($column)) {
            return $type;
        }

        if (preg_match('/(^|_)(image|avatar|thumbnail|photo|cover|logo)(_url|_path)?$/i', $column)) {
            return 'image';
        }

        $meta = $this->getTableColumns($model->getTable())[$column] ?? null;

        return $meta ? $this->mapDatabaseType($meta) : 'text';
    }

    protected function typeFromCast(string $column): ?string
    {
        $model = $this->model();
        $casts = $model->getCasts();

        if ( ! isset($casts[$column])) {
            return in_array($column, $model->getDates(), true) ? 'datetime' : null;
        }

        $cast = strtolower((string) $casts[$column]);
        $base = explode(':', $cast, 2)[0];

        return match (true) {
            in_array($base, ['bool', 'boolean'], true)                                                              => 'boolean',
            in_array($base, ['int', 'integer', 'float', 'double', 'real', 'decimal'], true)                         => 'number',
            in_array($base, ['date', 'immutable_date'], true)                                                       => 'date',
            in_array($base, ['datetime', 'immutable_datetime', 'custom_datetime', 'immutable_custom_datetime', 'timestamp'], true) => 'datetime',
            in_array($base, ['array', 'json', 'object', 'collection'], true),
            str_contains($cast, 'asarrayobject'),
            str_contains($cast, 'ascollection'),
            str_contains($cast, 'asencryptedarrayobject')                                                           => 'json',
            default                                                                                                 => null,
        };
    }

    /**
     * Map Schema::getColumns() metadata to a header type.
     */
    protected function mapDatabaseType(array $meta): string
    {
        $typeName = strtolower((string) ($meta['type_name'] ?? ''));
        $fullType = strtolower((string) ($meta['type'] ?? ''));

        return match (true) {
            $fullType === 'tinyint(1)', in_array($typeName, ['bool', 'boolean'], true) => 'boolean',
            in_array($typeName, ['tinyint', 'smallint', 'mediumint', 'int', 'integer', 'bigint', 'decimal', 'numeric', 'float', 'double', 'real', 'int2', 'int4', 'int8', 'float4', 'float8'], true) => 'number',
            $typeName === 'date' => 'date',
            in_array($typeName, ['datetime', 'datetime2', 'datetimeoffset', 'timestamp', 'timestamptz'], true) => 'datetime',
            in_array($typeName, ['json', 'jsonb'], true) => 'json',
            in_array($typeName, ['text', 'tinytext', 'mediumtext', 'longtext', 'clob'], true) => 'longtext',
            default => 'text',
        };
    }

    protected function alignFor(string $type): string
    {
        return match ($type) {
            'number'  => 'end',
            'boolean' => 'center',
            default   => 'start',
        };
    }

    /**
     * created_at → "Created at", startDate → "Start date", user_id → "User ID".
     */
    protected function humanizeColumn(string $column): string
    {
        $words = trim(str_replace('_', ' ', Str::snake($column)));

        return Str::ucfirst(preg_replace('/\bid\b/', 'ID', $words));
    }

    /**
     * Searchable columns that actually exist (as table column or translation).
     */
    protected function resolveSearchableColumns(): array
    {
        return array_values(array_unique(array_filter(
            $this->getSearchableColumns(),
            fn ($column) => is_string($column) && $this->isQueryableColumn($column)
        )));
    }

    /**
     * Columns accepted by the advanced (column/operator/value) filter.
     */
    protected function getFilterableColumns(): array
    {
        return array_values(array_filter(
            $this->getDisplayableColumns(),
            fn ($column) => $this->isQueryableColumn($column)
        ));
    }

    protected function resolvePerPage(Request $request): int
    {
        $requested = null;

        foreach (['per_page', 'itemsPerPage', 'itemsLength'] as $key) {
            $value = $request->input($key);

            if (is_numeric($value) && (int) $value !== 0) {
                $requested = (int) $value;
                break;
            }
        }

        if ($requested === null) {
            return $this->defaultPerPage;
        }

        $options = $this->perPageOptions;
        sort($options);

        // Negative = Vuetify's "All" → the largest allowed page.
        if ($requested < 0) {
            return end($options);
        }

        $nearest = $options[0];
        foreach ($options as $option) {
            // Strict "<" keeps the smaller option on ties (e.g. 75 → 50).
            if (abs($requested - $option) < abs($requested - $nearest)) {
                $nearest = $option;
            }
        }

        return $nearest;
    }

    protected function resolvePage(Request $request): int
    {
        $page = $request->input('page');

        return is_numeric($page) ? max(1, (int) $page) : 1;
    }

    /**
     * Effective [column, direction].
     */
    protected function resolveSort(Request $request): array
    {
        $sortBy = $request->input('sort_by');

        if (is_string($sortBy) && in_array($sortBy, $this->getSortableColumns(), true)) {
            $sortDir = $request->input('sort_dir');

            return [$sortBy, is_string($sortDir) && strtolower($sortDir) === 'desc' ? 'desc' : 'asc'];
        }

        $column    = $this->defaultSort[0] ?? $this->model()->getKeyName();
        $direction = strtolower((string) ($this->defaultSort[1] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        if ( ! is_string($column) || ! $this->isQueryableColumn($column)) {
            $column = $this->model()->getKeyName();
        }

        return [$column, $direction];
    }

    protected function applySort(Builder $builder, string $column, string $direction): Builder
    {
        $model = $builder->getModel();

        if ($this->isTranslatedColumn($column)) {
            $builder->orderByTranslation($column, $direction);
        } else {
            $builder->orderBy($model->qualifyColumn($column), $direction);
        }

        // Stable pages when the sort column has duplicates.
        if ($column !== $model->getKeyName()) {
            $builder->orderBy($model->getQualifiedKeyName(), $direction);
        }

        return $builder;
    }

    protected function resolveSearchTerm(Request $request): ?string
    {
        $term = $request->input('search');

        if ( ! is_scalar($term)) {
            return null;
        }

        $term = trim((string) $term);

        return $term === '' ? null : Str::limit($term, 255, '');
    }

    /**
     * OR the term across the searchable columns (+ exact primary key when numeric).
     */
    protected function applyFreeTextSearch(Builder $builder, string $term): Builder
    {
        $model   = $builder->getModel();
        $columns = $this->resolveSearchableColumns();
        $isKey   = ctype_digit($term) && strlen($term) <= 18;

        if ($columns === [] && ! $isKey) {
            return $builder->whereRaw('1 = 0');
        }

        $pattern = '%' . $this->escapeLike($term) . '%';

        return $builder->where(function (Builder $query) use ($columns, $pattern, $model, $isKey, $term) {
            foreach ($columns as $column) {
                if ($this->isTranslatedColumn($column)) {
                    $query->orWhereHas('translations', function (Builder $translations) use ($column, $pattern) {
                        $this->whereLike($translations, $translations->getModel()->qualifyColumn($column), $pattern);
                    });
                } else {
                    $this->whereLike($query, $model->qualifyColumn($column), $pattern, 'or');
                }
            }

            if ($isKey) {
                $query->orWhere($model->getQualifiedKeyName(), (int) $term);
            }
        });
    }

    /**
     * LIKE with an explicit escape character so % and _ in user input are
     * literal on MySQL and SQLite alike.
     */
    protected function whereLike(Builder $query, string $qualifiedColumn, string $pattern, string $boolean = 'and'): Builder
    {
        $wrapped = $query->getQuery()->getGrammar()->wrap($qualifiedColumn);

        return $query->whereRaw("{$wrapped} LIKE ? ESCAPE '" . self::LIKE_ESCAPE . "'", [$pattern], $boolean);
    }

    protected function escapeLike(string $value): string
    {
        $e = self::LIKE_ESCAPE;

        return str_replace([$e, '%', '_'], [$e . $e, $e . '%', $e . '_'], $value);
    }

    /**
     * Split "1,2, 3" into unique keys; non-numeric ids are dropped for integer keys.
     */
    protected function parseIds($ids): array
    {
        $integerKeys = in_array($this->model()->getKeyType(), ['int', 'integer'], true);

        return collect(explode(',', (string) $ids))
            ->map(fn ($id) => trim($id))
            ->filter(fn ($id) => $id !== '' && ( ! $integerKeys || ctype_digit($id)))
            ->map(fn ($id) => $integerKeys ? (int) $id : $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * If the request has the columns required to search.
     *
     *
     * @return bool
     */
    protected function hasSearchQuery(Request $request)
    {
        foreach (['column', 'operator', 'value'] as $key) {
            $value = $request->input($key);

            if ( ! is_scalar($value) || trim((string) $value) === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Resolve the given operator to perform a query.
     *
     * @param  string  $operator
     * @return array|null
     */
    protected function resolveQueryParts($operator, $value)
    {
        $like = is_scalar($value) ? $this->escapeLike((string) $value) : '';

        return Arr::get([
            'equals' => [
                'operator' => '=',
                'value'    => $value,
            ],
            'contains' => [
                'operator' => 'LIKE',
                'value'    => "%{$like}%",
            ],
            'starts_with' => [
                'operator' => 'LIKE',
                'value'    => "{$like}%",
            ],
            'ends_with' => [
                'operator' => 'LIKE',
                'value'    => "%{$like}",
            ],
            'greater_than' => [
                'operator' => '>',
                'value'    => $value,
            ],
            'less_than' => [
                'operator' => '<',
                'value'    => $value,
            ],
            'greater_than_or_equal_to' => [
                'operator' => '>=',
                'value'    => $value,
            ],
            'less_than_or_equal_to' => [
                'operator' => '<=',
                'value'    => $value,
            ],
        ], is_string($operator) ? $operator : '');
    }

    /**
     * Build the advanced (column/operator/value) filter. Unknown columns or
     * operators are ignored; comparison values are coerced to the column type
     * and a value that can never match (e.g. "abc" for a date) matches nothing.
     *
     *
     * @return Builder
     */
    protected function buildSearch(Builder $builder, Request $request)
    {
        $column     = $request->input('column');
        $queryParts = $this->resolveQueryParts($request->input('operator'), $request->input('value'));

        if ( ! is_array($queryParts) || ! is_string($column) || ! in_array($column, $this->getFilterableColumns(), true)) {
            return $builder;
        }

        if ($queryParts['operator'] !== 'LIKE') {
            $queryParts = $this->normalizeFilterValue($column, $queryParts);

            if ($queryParts === null) {
                // Match nothing instead of letting the database reject the value
                // (MySQL strict mode errors on e.g. created_at = 'abc').
                return $builder->whereRaw('1 = 0');
            }
        }

        if ($this->isTranslatedColumn($column)) {
            return $builder->whereHas('translations', function (Builder $translations) use ($column, $queryParts) {
                $this->applyCondition($translations, $translations->getModel()->qualifyColumn($column), $queryParts);
            });
        }

        return $this->applyCondition($builder, $builder->getModel()->qualifyColumn($column), $queryParts);
    }

    protected function applyCondition(Builder $builder, string $qualifiedColumn, array $queryParts): Builder
    {
        if ($queryParts['operator'] === 'LIKE') {
            return $this->whereLike($builder, $qualifiedColumn, $queryParts['value']);
        }

        if ( ! empty($queryParts['date_only'])) {
            return $builder->whereDate($qualifiedColumn, $queryParts['operator'], $queryParts['value']);
        }

        return $builder->where($qualifiedColumn, $queryParts['operator'], $queryParts['value']);
    }

    /**
     * Coerce a comparison (non-LIKE) filter value to the column's header type.
     * Returns null when the value can never match that type.
     */
    protected function normalizeFilterValue(string $column, array $queryParts): ?array
    {
        $type  = $this->resolveColumnTypes()[$column] ?? 'text';
        $value = trim((string) $queryParts['value']);

        switch ($type) {
            case 'id':
            case 'number':
                return is_numeric($value) ? [...$queryParts, 'value' => $value + 0] : null;

            case 'boolean':
                $bool = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

                return $bool === null ? null : [...$queryParts, 'value' => (int) $bool];

            case 'date':
            case 'datetime':
                if ( ! preg_match('/^\d{4}-\d{2}-\d{2}(?:[ T]\d{2}:\d{2}(?::\d{2})?)?$/', $value)) {
                    return null;
                }

                try {
                    $date = Carbon::parse($value);
                } catch (\Throwable) {
                    return null;
                }

                // The header type may be overridden (e.g. a timestamp shown as a
                // date), so compare according to what the column really stores.
                $storage  = $this->detectColumnType($column);
                $wholeDay = $storage === 'datetime' && (strlen($value) === 10 || $type === 'date');

                return [
                    ...$queryParts,
                    'value'     => $storage === 'date' || $wholeDay ? $date->format('Y-m-d') : $date->format('Y-m-d H:i:s'),
                    // "created_at equals 2026-09-11" means that whole day.
                    'date_only' => $wholeDay,
                ];

            default:
                return $queryParts;
        }
    }
}
