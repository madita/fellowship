<?php

namespace App\Http\Controllers\DataTable;

use Illuminate\Http\Response;
use Spatie\Permission\Models\Role;
use Illuminate\Http\Request;
use App\Http\Controllers\DataTable\DataTableController;

class RoleController extends DataTableController
{
    protected $allowCreation = true;

    public function builder()
    {
        return Role::query();
    }

    public function getCustomColumnsNames()
    {
        return [
            'name'         => 'Name',
            'display_name' => 'Display Name',
        ];
    }

    public function getDisplayableColumns()
    {
        return [
            'id',
            'name',
            'display_name',
            'created_at',
        ];
    }

    public function getUpdatableColumns()
    {
        return [
            'name',
            'display_name',
        ];
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

        $create = $request->only($this->getUpdatableColumns());
        $create['guard_name'] = 'api';

        $this->builder->create($create);
    }

    public function update($id, Request $request)
    {
//        $this->validate($request, [
//            'name' => 'required',
//            'email' => 'required|unique:users,email,' . $id . '|email',
//            'created_at' => 'date'
//        ]);
//
        $this->builder->find($id)->update($request->only($this->getUpdatableColumns()));
    }
}
