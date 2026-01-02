<?php

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Hash;
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

Route::resource('wiki', "\App\Http\Controllers\WikiController");
Route::post('wiki/category', "\App\Http\Controllers\WikiController@storeCategory");
Route::patch('wiki/category/{slug}', "\App\Http\Controllers\WikiController@updateCategory");
Route::get('wiki-pages', "\App\Http\Controllers\WikiController@getPages");

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    $user = $request->user();
    $permissions = $user->getAllPermissions()->pluck('name');
    $roles = $user->roles()->pluck('name');

    return ['user' => $user, 'roles' => $roles, 'permissions' => $permissions];
});

Route::post('/users/search', "\App\Http\Controllers\UserController@searchUsers");

//
Route::group(['prefix' => '/account', 'middleware' => ['auth:sanctum'], 'as' => 'account.'], function () {
    Route::get('/notifications', 'App\Http\Controllers\NotificationController@index')->name('notification.index');
    Route::get('/notification', 'App\Http\Controllers\NotificationController@notification')->name('notification.unread');
    Route::delete('/notification/delete/{id}', 'App\Http\Controllers\NotificationController@notificationdelete');
    Route::get('/notification/allasread', 'App\Http\Controllers\NotificationController@notificationread');
    Route::get('/notification/markasread/{id}', 'App\Http\Controllers\NotificationController@notificationsingleread');

    Route::post('/avatar', 'App\Http\Controllers\UserController@uploadAvatar');
    Route::patch('/preferences', 'App\Http\Controllers\UserController@updatePreferences');
});

Route::group(['prefix' => '/chat', 'middleware' => ['auth:sanctum']], function () {
//    Route::get('/', 'App\Http\Controllers\Chat\ChatController@index')->name('chat');
    Route::get('/messages', 'App\Http\Controllers\Chat\ChatMessageController@index');
    Route::post('/messages', 'App\Http\Controllers\Chat\ChatMessageController@store');
});

Route::get('/tag/taxonomies', '\App\Http\Controllers\TaxonomyController@getTaxonomies');
Route::get('/tag/terms/{taxonomy?}', '\App\Http\Controllers\TaxonomyController@getTerms');
Route::get('/taxables', '\App\Http\Controllers\TaxonomyController@getTaxables');
Route::post('/tag/terms/', '\App\Http\Controllers\TaxonomyController@saveTerms');
Route::get('/tag/{term}/{taxonomy?}', '\App\Http\Controllers\TaxonomyController@getTermInfo');

Route::get('/pages/{slug}', '\App\Http\Controllers\PageController@view');
Route::get('/pages/{page}/history', '\App\Http\Controllers\PageController@history');
//Route::get('/pages/tag/{term}', '\App\Http\Controllers\PageController@showWithTerm');
//Route::get('/pages/{taxonomy}/{category}', '\App\Http\Controllers\PageController@showWithCategory');
Route::get('/posts/{slug}', '\App\Http\Controllers\PostController@view');

Route::group(['middleware' => ['auth:sanctum']], function () {
    Route::get('/pages/{page}/edit', '\App\Http\Controllers\PageController@show');
    Route::patch('/pages/{page}/edit', '\App\Http\Controllers\PageController@update');

    Route::get('/posts/{page}/edit', '\App\Http\Controllers\PostController@show');
    Route::patch('/posts/{page}/edit', '\App\Http\Controllers\PostController@update');

    Route::get('/events/{event}/going/{answer}', "\App\Http\Controllers\EventController@isGoing");
    Route::get('/events/types', "\App\Http\Controllers\EventController@getTypes");
    Route::post('/events/{event}/answer', "\App\Http\Controllers\EventController@joinEvent");
//    Route::resource('events', "\App\Http\Controllers\EventController");
    Route::get('events/create', ['as' => 'event.create', 'uses' => "\App\Http\Controllers\EventController@create"]);
    Route::get('events', ['as' => 'event.index', 'uses' => "\App\Http\Controllers\EventController@index"]);
    Route::post('events', ['as' => 'event.store', 'uses' => "\App\Http\Controllers\EventController@store"]);
    Route::get('events/{event}', ['as' => 'event.show', 'uses' => "\App\Http\Controllers\EventController@show"]);
    Route::patch('events/{event}', ['as' => 'event.update', 'uses' => "\App\Http\Controllers\EventController@update"]);
    Route::delete('events/{event}', ['as' => 'event.destroy', 'uses' => "\App\Http\Controllers\EventController@destroy"]);
    Route::get('events/{event}/edit', ['as' => 'event.edit', 'uses' => "\App\Http\Controllers\EventController@edit"]);
    Route::post('events/{event}/approve-guest', ['as' => 'event.approve', 'uses' => "\App\Http\Controllers\EventController@approveGuest"]);
});

Route::get('/collections', [App\Http\Controllers\CollectionController::class, 'index']); // Fetch all collections
Route::get('/collections/{collection}', [App\Http\Controllers\CollectionController::class, 'show']); // Fetch media for a specific collection
Route::post('/collections', [App\Http\Controllers\CollectionController::class, 'store']); // Create a new collection
Route::post('/collections/{collection}', [App\Http\Controllers\CollectionController::class, 'uploadMedia']); // Upload media to collection
Route::patch('/media/{media}/caption', [App\Http\Controllers\CollectionController::class, 'updateMediaCaption']); // Update caption for a media item
Route::delete('/collections/{collection}', [App\Http\Controllers\CollectionController::class, 'delete']); // Delete collection
Route::delete('/media/{media}', [App\Http\Controllers\CollectionController::class, 'deleteMedia']); // Delete a media item

Route::group(['middleware' => ['auth:sanctum']], function () {
    //Route::group(['middleware' => ['role_or_permission:admin|manage-*']], function () {
    Route::resource('datatable/pages', 'App\Http\Controllers\DataTable\PageController');
//    Route::get('datatable/pages/categories/{taxonomy}', 'App\Http\Controllers\DataTable\PageController@getCategories');
    Route::resource('datatable/posts', 'App\Http\Controllers\DataTable\PostController');
    Route::resource('datatable/users', 'App\Http\Controllers\DataTable\UserController');
    Route::resource('datatable/roles', 'App\Http\Controllers\DataTable\RoleController');
    Route::resource('datatable/taxonomies', 'App\Http\Controllers\DataTable\TaxonomyController');
    Route::resource('datatable/terms', 'App\Http\Controllers\DataTable\TermController');
    Route::get('datatable/permissions/roles', 'App\Http\Controllers\DataTable\PermissionController@roles');
    Route::post('datatable/permissions/roles', 'App\Http\Controllers\DataTable\PermissionController@updateRolePermissions');
    Route::get('datatable/permissions/permissions', 'App\Http\Controllers\DataTable\PermissionController@permissions');
    Route::resource('datatable/permissions', 'App\Http\Controllers\DataTable\PermissionController');
    Route::resource('datatable/events', 'App\Http\Controllers\DataTable\EventController');
    Route::resource('datatable/event-types', 'App\Http\Controllers\DataTable\EventTypeController');
    Route::resource('datatable/event-profiles', 'App\Http\Controllers\DataTable\EventProfileController');
});

Route::group(['middleware' => ['auth:sanctum']], function () {
    //Route::get('', 'ConversationController@index');
    //Route::post('', 'ConversationController@store');
    //Route::get('/{conversation}', 'ConversationController@show');
    Route::resource('conversations', 'App\Http\Controllers\Conversation\ConversationController');
    Route::post('/conversations/{conversation}/reply', 'App\Http\Controllers\Conversation\ConversationReplyController@store');
    Route::post('/conversations/{conversation}/users', 'App\Http\Controllers\Conversation\ConversationUserController@store');
    Route::post('/conversations/{conversation}/mark-as-read', 'App\Http\Controllers\Conversation\ConversationController@markAsRead');
});

Route::get('/models', [App\Http\Controllers\RelateableController::class, 'getModels']);
Route::get('/source-models', [App\Http\Controllers\RelateableController::class, 'getSourceModels']);
Route::get('/model-items', [App\Http\Controllers\RelateableController::class, 'getModelItems']);
Route::post('/relate-models', [App\Http\Controllers\RelateableController::class, 'relateModels']);
Route::post('/related-items', [App\Http\Controllers\RelateableController::class, 'getRelatedItems']);

Route::get('/common/items', [App\Http\Controllers\CommonController::class, 'getItems']);

// Public Settings Route (for unauthenticated access to public settings like app name and logo)
Route::get('/settings/public', 'App\Http\Controllers\Admin\SettingsController@public');

// Public Homepage Routes (for homepage rendering)
Route::get('/homepage/widgets', 'App\Http\Controllers\Admin\HomepageWidgetController@getActiveWidgets');
Route::get('/homepage/menu', 'App\Http\Controllers\Admin\HomepageMenuController@getActiveMenu');
Route::get('/homepage/sections', 'App\Http\Controllers\Admin\HomepageSectionController@getActiveSections');

// Admin Settings Routes
Route::group(['prefix' => 'admin', 'middleware' => ['auth:sanctum']], function () {
    // Settings
    Route::get('/settings', 'App\Http\Controllers\Admin\SettingsController@index');
    Route::post('/settings', 'App\Http\Controllers\Admin\SettingsController@update');
    Route::post('/settings/logo', 'App\Http\Controllers\Admin\SettingsController@uploadLogo');
    Route::delete('/settings/logo', 'App\Http\Controllers\Admin\SettingsController@deleteLogo');
    Route::post('/settings/image', 'App\Http\Controllers\Admin\SettingsController@uploadImage');
    Route::delete('/settings/image', 'App\Http\Controllers\Admin\SettingsController@deleteImage');
    Route::post('/settings/test-email', 'App\Http\Controllers\Admin\SettingsController@testEmail');

    // Homepage Widgets
    Route::get('/homepage/widgets', 'App\Http\Controllers\Admin\HomepageWidgetController@index');
    Route::post('/homepage/widgets', 'App\Http\Controllers\Admin\HomepageWidgetController@store');
    Route::patch('/homepage/widgets/{id}', 'App\Http\Controllers\Admin\HomepageWidgetController@update');
    Route::delete('/homepage/widgets/{id}', 'App\Http\Controllers\Admin\HomepageWidgetController@destroy');
    Route::post('/homepage/widgets/reorder', 'App\Http\Controllers\Admin\HomepageWidgetController@updateOrder');
    Route::post('/homepage/widgets/{id}/toggle', 'App\Http\Controllers\Admin\HomepageWidgetController@toggle');
    Route::post('/homepage/widgets/{id}/duplicate', 'App\Http\Controllers\Admin\HomepageWidgetController@duplicate');

    // Homepage Menu
    Route::get('/homepage/menu', 'App\Http\Controllers\Admin\HomepageMenuController@index');
    Route::post('/homepage/menu', 'App\Http\Controllers\Admin\HomepageMenuController@store');
    Route::patch('/homepage/menu/{id}', 'App\Http\Controllers\Admin\HomepageMenuController@update');
    Route::delete('/homepage/menu/{id}', 'App\Http\Controllers\Admin\HomepageMenuController@destroy');
    Route::post('/homepage/menu/reorder', 'App\Http\Controllers\Admin\HomepageMenuController@updateOrder');

    // Homepage Sections
    Route::get('/homepage/sections', 'App\Http\Controllers\Admin\HomepageSectionController@index');
    Route::post('/homepage/sections', 'App\Http\Controllers\Admin\HomepageSectionController@store');
    Route::patch('/homepage/sections/{id}', 'App\Http\Controllers\Admin\HomepageSectionController@update');
    Route::delete('/homepage/sections/{id}', 'App\Http\Controllers\Admin\HomepageSectionController@destroy');
    Route::post('/homepage/sections/reorder', 'App\Http\Controllers\Admin\HomepageSectionController@updateOrder');
    Route::post('/homepage/sections/{id}/toggle', 'App\Http\Controllers\Admin\HomepageSectionController@toggle');
});

Route::post('/login', function (Request $request) {
    $data = $request->validate([
        'email'    => 'required|email',
        'password' => 'required',
    ]);

    $user = User::where('email', $request->email)->first();

    if (!$user || !Hash::check($request->password, $user->password)) {
        return response([
            'message' => ['These credentials do not match our records.'],
        ], 404);
    }

    $token = $user->createToken('my-app-token')->plainTextToken;

    $response = [
        'user'  => $user,
        'token' => $token,
    ];

    return response($response, 201);
});
