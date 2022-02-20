<?php
use Illuminate\Support\Facades\Broadcast;
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
//Broadcast::routes(['middleware' => ['auth:sanctum']]);

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    $user = $request->user();
    $permissions = $user->getAllPermissions()->pluck('name');
    $roles = $user->roles()->pluck('name');

    return ['user' => $user, 'roles' => $roles, 'permissions' => $permissions];
});

//
Route::group(['prefix' => '/account', 'middleware' => ['auth:sanctum'], 'as' => 'account.'], function () {

    Route::get('/notifications', 'App\Http\Controllers\NotificationController@index')->name('notification.index');
    Route::get('/notification', 'App\Http\Controllers\NotificationController@notification')->name('notification.unread');
    Route::delete('/notification/delete/{id}', 'App\Http\Controllers\NotificationController@notificationdelete');
    Route::get('/notification/allasread', 'App\Http\Controllers\NotificationController@notificationread');
    Route::get('/notification/markasread/{id}', 'App\Http\Controllers\NotificationController@notificationsingleread');
});

Route::group(['prefix' => '/chat', 'middleware' => ['auth:sanctum']], function () {
//    Route::get('/', 'App\Http\Controllers\Chat\ChatController@index')->name('chat');
    Route::get('/messages', 'App\Http\Controllers\Chat\ChatMessageController@index');
    Route::post('/messages', 'App\Http\Controllers\Chat\ChatMessageController@store');

});

Route::get('/pages/{slug}', '\App\Http\Controllers\PageController@view');
Route::get('/pages/{page}/edit', '\App\Http\Controllers\PageController@show');
Route::patch('/pages/{page}/edit', '\App\Http\Controllers\PageController@update');

//Route::group(['prefix' => '/pages', 'middleware' => ['auth:sanctum']], function () {
//    Route::get('/', 'App\Http\Controllers\Chat\ChatController@index')->name('chat');
//    Route::get('/messages', 'App\Http\Controllers\Chat\ChatMessageController@index');
//    Route::post('/messages', 'App\Http\Controllers\Chat\ChatMessageController@store');
//
//});



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


