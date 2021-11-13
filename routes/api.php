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
    return $request->user();
});

Route::resource('datatable/pages', 'App\Http\Controllers\DataTable\PageController');
Route::resource('datatable/posts', 'App\Http\Controllers\DataTable\PostController');
Route::resource('datatable/users', 'App\Http\Controllers\DataTable\UserController');




