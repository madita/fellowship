<?php

namespace App\Http\Controllers\DataTable;

use App\Models\User;
use Illuminate\Http\Request;

class UserController extends DataTableController
{
    protected $allowCreation = false;

    public function builder()
    {
        return User::query();
    }

    public function getCustomColumnsNames()
    {
        return [

        ];
    }

    public function getDisplayableColumns()
    {
        return [
            'id',
            'name',
            'created_at',
        ];
    }

    public function getUpdatableColumns()
    {
        return  [
            'name',
            'email',
        ];
    }

    public function update($id, Request $request)
    {
        $this->validate($request, [
            'name'       => 'required',
            'email'      => 'required|unique:users,email,'.$id.'|email',
            'created_at' => 'date',
        ]);

        $this->builder->find($id)->update($request->only($this->getUpdatableColumns()));
    }

//    public function getAppends()
//    {
//        return [
//            'isAdmin'
//        ];
//    }
}
