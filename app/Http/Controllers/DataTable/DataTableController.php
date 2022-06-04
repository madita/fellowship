<?php

namespace App\Http\Controllers\DataTable;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

abstract class DataTableController extends Controller
{
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

    /*Does Edit Form for model exist?*/
    protected $hasForm = false;

    /**
     * The entity builder.
     *
     * @var Builder
     */
    protected $builder;

    /**
     * Get the columns that are allowed to be displayed.
     *
     * @return array
     */
    public function getDisplayableColumns()
    {
        return array_diff(
            $this->getDatabaseColumnNames(),
            $this->builder->getModel()->getHidden()
        );
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
     * Create the controller, check builder method and assign
     * to the builder property.
     *
     * @return void
     */
    public function __construct()
    {
        if (!method_exists($this, 'builder')) {
            throw new Exception('No entity builder method defined.');
        }

        if (!($this->builder = $this->builder()) instanceof Builder) {
            throw new Exception('Entity builder not instance of Builder.');
        }
    }

    public function getHeaders()
    {
        $columnNames = $this->getCustomColumnsNames();

        return collect($this->getDisplayableColumns())->map(function ($column) use ($columnNames) {
            return [
                'text'     => isset($columnNames[$column]) ? $columnNames[$column] : $column,
                'sortable' => false,
                'value'    => $column,
            ];
        })->add([
            'text'     => 'Actions',
            'sortable' => false,
            'value'    => 'actions',
        ]);
    }

    /**
     * Get records to be used for output.
     *
     * @param Request $request
     *
     * @return Collection
     */
    public function getRecords(Request $request)
    {
        $builder = $this->builder;

        if ($this->hasSearchQuery($request)) {
            $builder = $this->buildSearch($builder, $request);
        }

        try {
            //if model has appended attributes and append attributes  not in displayable colimns...forget them
            $forget = array_diff($this->getAppends(), $this->getDisplayableColumns());
            $pagination = (int) $request->get('itemsPerPage') <= 0 ? (int) $request->get('itemsLength') : (int) $request->get('itemsPerPage');

            return $builder->orderBy('id', 'asc')->get()->makeHidden($forget)->paginate($pagination);
        } catch (QueryException $e) {
            return collect([]);
        }
    }

    /**
     * Show a list of entities.
     *
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => [
                'table'         => $this->builder->getModel()->getTable(),
                'headers'       => $this->getHeaders(),
                'records'       => $this->getRecords($request),
                'updatable'     => array_values($this->getUpdatableColumns()),
                'displayable'   => array_values($this->getDisplayableColumns()),
                'column_map'    => $this->getCustomColumnsNames(),
                'column_fields' => $this->getCustomInputFields(),
                'allow'         => [
                    'hasForm'  => $this->hasForm,
                    'creation' => $this->allowCreation,
                    'deletion' => $this->allowDeletion,
                ],
            ],
        ]);
    }

    /**
     * Create an entity.
     *
     * @param Request $request
     *
     * @return Response|void
     */
    public function store(Request $request)
    {
        if (!$this->allowCreation) {
            return;
        }

        $this->builder->create($request->only($this->getUpdatableColumns()));
    }

    /**
     * Update an entity.
     *
     * @param int     $id
     * @param Request $request
     *
     * @return Response
     */
    public function update($id, Request $request)
    {
       return $this->builder->find($id)->update($request->only($this->getUpdatableColumns()));
    }

    /**
     * Delete an entity.
     *
     * @param int     $id
     * @param Request $request
     *
     * @return Response|void
     */
    public function destroy($ids, Request $request)
    {
        if (!$this->allowDeletion) {
            return;
        }

        $this->builder->whereIn('id', explode(',', $ids))->delete();
    }

    /**
     * Get the database column names for the entity.
     *
     * @return array
     */
    protected function getDatabaseColumnNames(): array
    {
        return array_merge(Schema::getColumnListing($this->builder->getModel()->getTable()), $this->getAppends());
    }

    public function getCustomInputFields()
    {
        return [];
    }

    /**
     * If the request has the columns required to search.
     *
     * @param Request $request
     *
     * @return bool
     */
    protected function hasSearchQuery(Request $request)
    {
        return count(array_filter($request->only(['column', 'operator', 'value']))) === 3;
    }

    /**
     * Resolve the given operator to perform a query.
     *
     * @param string $operator
     *
     * @return string
     */
    protected function resolveQueryParts($operator, $value)
    {
        return Arr::get([
            'equals' => [
                'operator' => '=',
                'value'    => $value,
            ],
            'contains' => [
                'operator' => 'LIKE',
                'value'    => "%{$value}%",
            ],
            'starts_with' => [
                'operator' => 'LIKE',
                'value'    => "{$value}%",
            ],
            'ends_with' => [
                'operator' => 'LIKE',
                'value'    => "%{$value}",
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
        ], $operator);
    }

    /**
     * Build the search.
     *
     * @param Builder $builder
     * @param Request $request
     *
     * @return Builder
     */
    protected function buildSearch(Builder $builder, Request $request)
    {
        $queryParts = $this->resolveQueryParts($request->operator, $request->value);

        /** @noinspection PhpIllegalStringOffsetInspection */
        return $builder->where(
            $request->column,
            $queryParts['operator'],
            $queryParts['value']
        );
    }

    public function getAppends()
    {
        return [];
    }

    public function getCategories($taxonomy)
    {
        $this->builder->getModel()->getTerms($taxonomy);
    }
}
