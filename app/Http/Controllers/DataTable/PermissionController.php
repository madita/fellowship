<?php

namespace App\Http\Controllers\DataTable;

use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionController extends DataTableController
{
    protected $allowCreation = false;

    public function builder()
    {
        return Permission::query();
    }

    public function roles()
    {
        $roles = Role::with('permissions')->get();

        return response()->json([
            'data' => $roles,
        ]);
    }

    public function updateRolePermissions(Request $request)
    {
        //Todo make PermissionRequest validation
        //dd($request);

        $roles = collect($request)->groupBy('role')->map(function ($role) {
            return collect($role)->map(function ($item) {
                return $item['permission'];
            });
        });
//        dd($roles);
        foreach ($roles as $roleId => $rolePermissions) {
            $role = Role::findById($roleId, 'api');
            $role->syncPermissions($rolePermissions);
//            dd($rolePermissions);
//            Permission::findById()
        }
//        $roles = Role::with('permissions')->get();
        return response()->json([
            'data' => 'done',
        ]);
    }

    public function permissions()
    {
        $permissions = Permission::all();

        return response()->json([
            'data' => $permissions,
        ]);
    }

    public function show()
    {
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
            'description',
            'category',
            'created_at',
        ];
    }

    public function getUpdatableColumns()
    {
        return [
            'name',
        ];
    }

    public function update($id, Request $request)
    {
        $this->builder->find($id)->update($request->only($this->getUpdatableColumns()));
    }
}
