<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    $user = $request->user();
    $permissions = $user->getAllPermissions()->pluck('name');
    $roles = $user->roles()->pluck('name');

    return ['user' => $user, 'roles' => $roles, 'permissions' => $permissions];
});


Route::group(['middleware' => ['role_or_permission:admin|manage-*']], function () {
    Route::resource('datatable/pages', 'App\Http\Controllers\DataTable\PageController');
    Route::resource('datatable/posts', 'App\Http\Controllers\DataTable\PostController');
    Route::resource('datatable/users', 'App\Http\Controllers\DataTable\UserController');
    Route::resource('datatable/roles', 'App\Http\Controllers\DataTable\RoleController');
    Route::get('datatable/permissions/roles', 'App\Http\Controllers\DataTable\PermissionController@roles');
    Route::post('datatable/permissions/roles', 'App\Http\Controllers\DataTable\PermissionController@updateRolePermissions');
    Route::get('datatable/permissions/permissions', 'App\Http\Controllers\DataTable\PermissionController@permissions');
    Route::resource('datatable/permissions', 'App\Http\Controllers\DataTable\PermissionController');
});


