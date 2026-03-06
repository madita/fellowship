<?php

use App\Models\User;
use Illuminate\Http\Request;
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

// Public cacheable routes
Route::middleware(['cache.control'])->group(function () {
    Route::resource('wiki', "\App\Http\Controllers\WikiController")->only(['index', 'show']);
    Route::get('wiki-pages', "\App\Http\Controllers\WikiController@getPages");
});

// Forum Routes (public read, auth for write)
Route::get('/forums', 'App\Http\Controllers\Forum\ForumController@index');
Route::get('/forums/search', 'App\Http\Controllers\Forum\ForumSearchController@search');
Route::get('/forums/recent-threads', 'App\Http\Controllers\Forum\ForumController@recentThreads');
Route::get('/forums/{slug}', 'App\Http\Controllers\Forum\ForumController@show');
Route::get('/forums/{forumSlug}/threads/{threadSlug}', 'App\Http\Controllers\Forum\ForumThreadController@show');
Route::get('/activity', 'App\Http\Controllers\ActivityController@index');

Route::group(['middleware' => ['auth:sanctum']], function () {
    // Forum management (admin only)
    Route::post('/forums', 'App\Http\Controllers\Forum\ForumController@store');
    Route::patch('/forums/{id}', 'App\Http\Controllers\Forum\ForumController@update');
    Route::delete('/forums/{id}', 'App\Http\Controllers\Forum\ForumController@destroy');

    // Thread management
    Route::post('/forums/{id}/threads', 'App\Http\Controllers\Forum\ForumThreadController@store');
    Route::patch('/threads/{thread}', 'App\Http\Controllers\Forum\ForumThreadController@update');
    Route::delete('/threads/{thread}', 'App\Http\Controllers\Forum\ForumThreadController@destroy');

    // Post management
    Route::post('/threads/{thread}/posts', 'App\Http\Controllers\Forum\ForumPostController@store');
    Route::patch('/posts/{post}', 'App\Http\Controllers\Forum\ForumPostController@update');
    Route::delete('/posts/{post}', 'App\Http\Controllers\Forum\ForumPostController@destroy');
    Route::post('/posts/{post}/mark-as-solution', 'App\Http\Controllers\Forum\ForumPostController@markAsSolution');

    // Thread subscriptions
    Route::post('/threads/{thread}/subscribe', 'App\Http\Controllers\Forum\ForumSubscriptionController@store');
    Route::delete('/threads/{thread}/subscribe', 'App\Http\Controllers\Forum\ForumSubscriptionController@destroy');

    // Post likes
    Route::post('/posts/{post}/like', 'App\Http\Controllers\Forum\ForumPostLikeController@store');
    Route::delete('/posts/{post}/like', 'App\Http\Controllers\Forum\ForumPostLikeController@destroy');
});

// Wiki write operations (not cached)
Route::resource('wiki', "\App\Http\Controllers\WikiController")->only(['store', 'update', 'destroy']);
Route::post('wiki/category', "\App\Http\Controllers\WikiController@storeCategory");
Route::patch('wiki/category/{slug}', "\App\Http\Controllers\WikiController@updateCategory");

// Public OAuth Providers endpoint (for login page)
Route::get('/settings/oauth-providers', 'App\Http\Controllers\Admin\SettingsController@getEnabledOAuthProviders');

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

    // Social Account Management
    Route::get('/social-accounts', [\App\Http\Controllers\SocialAccountController::class, 'index'])
        ->name('social-accounts.index');
    Route::delete('/social-accounts/{provider}', [\App\Http\Controllers\SocialAccountController::class, 'disconnect'])
        ->name('social-accounts.disconnect');
    Route::get('/social-accounts/{provider}/link', [\App\Http\Controllers\SocialAccountController::class, 'link'])
        ->name('social-accounts.link');
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

// Collaborative Sandbox
Route::prefix('sandbox')->group(function () {
    Route::get('/', [App\Http\Controllers\Sandbox\SandboxController::class, 'index'])->middleware('auth:sanctum');
    Route::post('/', [App\Http\Controllers\Sandbox\SandboxController::class, 'store'])->middleware('auth:sanctum');
    Route::get('/{slug}', [App\Http\Controllers\Sandbox\SandboxController::class, 'show']); // Public for public sandboxes
    
    Route::middleware('auth:sanctum')->group(function () {
        Route::put('/{sandbox}', [App\Http\Controllers\Sandbox\SandboxController::class, 'update']);
        Route::delete('/{sandbox}', [App\Http\Controllers\Sandbox\SandboxController::class, 'destroy']);
        
        // Collaboration state
        Route::get('/{sandbox}/state', [App\Http\Controllers\Sandbox\SandboxController::class, 'getState']);
        Route::post('/{sandbox}/state', [App\Http\Controllers\Sandbox\SandboxController::class, 'saveState']);
        
        // Collaborators
        Route::post('/{sandbox}/collaborators', [App\Http\Controllers\Sandbox\SandboxController::class, 'addCollaborator']);
        Route::delete('/{sandbox}/collaborators/{collaborator}', [App\Http\Controllers\Sandbox\SandboxController::class, 'removeCollaborator']);
        Route::post('/{sandbox}/accept-invite', [App\Http\Controllers\Sandbox\SandboxController::class, 'acceptInvite']);
        
        // Version history
        Route::get('/{sandbox}/versions', [App\Http\Controllers\Sandbox\SandboxController::class, 'versions']);
        Route::post('/{sandbox}/versions/{version}/restore', [App\Http\Controllers\Sandbox\SandboxController::class, 'restoreVersion']);
    });
});

Route::get('/models', [App\Http\Controllers\RelateableController::class, 'getModels']);
Route::get('/source-models', [App\Http\Controllers\RelateableController::class, 'getSourceModels']);
Route::get('/model-items', [App\Http\Controllers\RelateableController::class, 'getModelItems']);
Route::post('/relate-models', [App\Http\Controllers\RelateableController::class, 'relateModels']);
Route::post('/related-items', [App\Http\Controllers\RelateableController::class, 'getRelatedItems']);

Route::get('/common/items', [App\Http\Controllers\CommonController::class, 'getItems']);

// Cache test route
Route::get('/cache-test', function () {
    return response()->json([
        'cache_enabled'  => \App\Models\Setting::isCacheEnabled(),
        'cache_lifetime' => \App\Models\Setting::getCacheLifetime(),
        'time'           => now()->toDateTimeString(),
    ]);
})->middleware('cache.control');

// Public cacheable routes (settings, homepage, footer)
Route::middleware(['cache.control'])->group(function () {
    // Public Settings
    Route::get('/settings/public', 'App\Http\Controllers\Admin\SettingsController@public');
    Route::get('/settings/performance', 'App\Http\Controllers\Admin\SettingsController@performanceSettings');

    // Homepage
    Route::get('/homepage/widgets', 'App\Http\Controllers\Admin\HomepageWidgetController@getActiveWidgets');
    Route::get('/homepage/menu', 'App\Http\Controllers\Admin\HomepageMenuController@getActiveMenu');
    Route::get('/homepage/sections', 'App\Http\Controllers\Admin\HomepageSectionController@getActiveSections');

    // Footer
    Route::get('/footer/widgets', 'App\Http\Controllers\Admin\FooterWidgetController@getActiveWidgets');
    Route::get('/footer/sections', 'App\Http\Controllers\Admin\FooterSectionController@getActiveSections');
});

// Newsletter Subscription
Route::post('/newsletter/subscribe', 'App\Http\Controllers\NewsletterController@subscribe');

// API Keys Management (for users to manage their own API keys)
Route::group(['prefix' => 'api-keys', 'middleware' => ['auth:sanctum']], function () {
    Route::get('/status', 'App\Http\Controllers\Api\ApiKeyController@status');
    Route::get('/', 'App\Http\Controllers\Api\ApiKeyController@index');
    Route::post('/', 'App\Http\Controllers\Api\ApiKeyController@store');
    Route::get('/{id}', 'App\Http\Controllers\Api\ApiKeyController@show');
    Route::patch('/{id}', 'App\Http\Controllers\Api\ApiKeyController@update');
    Route::delete('/{id}', 'App\Http\Controllers\Api\ApiKeyController@destroy');
    Route::post('/{id}/regenerate', 'App\Http\Controllers\Api\ApiKeyController@regenerate');
});

// Account Deletion (GDPR Right to be Forgotten)
Route::group(['prefix' => 'account', 'middleware' => ['auth:sanctum']], function () {
    Route::get('/deletion/status', 'App\Http\Controllers\Api\AccountDeletionController@status');
    Route::get('/export-data', 'App\Http\Controllers\Api\AccountDeletionController@exportData');
    Route::post('/delete', 'App\Http\Controllers\Api\AccountDeletionController@requestDeletion');
});

// External API Routes (authenticated via API key with dynamic rate limiting)
Route::group(['prefix' => 'v1', 'middleware' => ['api.key', 'api.rate']], function () {
    // Example: Get current user info via API key
    Route::get('/me', function (Request $request) {
        return response()->json([
            'user'    => $request->user()->only(['id', 'name', 'username', 'email']),
            'api_key' => [
                'id'   => $request->attributes->get('api_key')->id,
                'name' => $request->attributes->get('api_key')->name,
            ],
        ]);
    });

    // Add more external API endpoints here as needed
    // These endpoints are accessible via API key authentication
});

// Admin Settings Routes
Route::group(['prefix' => 'admin', 'middleware' => ['auth:sanctum']], function () {
    // Settings
    Route::get('/settings', 'App\Http\Controllers\Admin\SettingsController@index');
    Route::post('/settings', 'App\Http\Controllers\Admin\SettingsController@update');
    Route::get('/settings/server-info', 'App\Http\Controllers\Admin\SettingsController@serverInfo');
    Route::post('/settings/logo', 'App\Http\Controllers\Admin\SettingsController@uploadLogo');
    Route::delete('/settings/logo', 'App\Http\Controllers\Admin\SettingsController@deleteLogo');
    Route::post('/settings/image', 'App\Http\Controllers\Admin\SettingsController@uploadImage');
    Route::delete('/settings/image', 'App\Http\Controllers\Admin\SettingsController@deleteImage');
    Route::post('/settings/test-email', 'App\Http\Controllers\Admin\SettingsController@testEmail');
    Route::get('/settings/cache-status', 'App\Http\Controllers\Admin\SettingsController@cacheStatus');
    Route::post('/settings/clear-cache', 'App\Http\Controllers\Admin\SettingsController@clearCache');

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

    // Homepage Image Upload
    Route::post('/homepage/upload-image', 'App\Http\Controllers\Admin\HomepageImageController@upload');
    Route::delete('/homepage/delete-image', 'App\Http\Controllers\Admin\HomepageImageController@delete');

    // Footer Widgets
    Route::get('/footer/widgets', 'App\Http\Controllers\Admin\FooterWidgetController@index');
    Route::post('/footer/widgets', 'App\Http\Controllers\Admin\FooterWidgetController@store');
    Route::patch('/footer/widgets/{id}', 'App\Http\Controllers\Admin\FooterWidgetController@update');
    Route::delete('/footer/widgets/{id}', 'App\Http\Controllers\Admin\FooterWidgetController@destroy');
    Route::post('/footer/widgets/reorder', 'App\Http\Controllers\Admin\FooterWidgetController@updateOrder');
    Route::post('/footer/widgets/{id}/toggle', 'App\Http\Controllers\Admin\FooterWidgetController@toggle');

    // Footer Sections
    Route::get('/footer/sections', 'App\Http\Controllers\Admin\FooterSectionController@index');
    Route::post('/footer/sections', 'App\Http\Controllers\Admin\FooterSectionController@store');
    Route::patch('/footer/sections/{id}', 'App\Http\Controllers\Admin\FooterSectionController@update');
    Route::delete('/footer/sections/{id}', 'App\Http\Controllers\Admin\FooterSectionController@destroy');
    Route::post('/footer/sections/reorder', 'App\Http\Controllers\Admin\FooterSectionController@updateOrder');
    Route::post('/footer/sections/{id}/toggle', 'App\Http\Controllers\Admin\FooterSectionController@toggle');

    // Media Center
    Route::get('/media', 'App\Http\Controllers\Admin\MediaController@index');
    Route::get('/media/folders', 'App\Http\Controllers\Admin\MediaController@folders');
    Route::get('/media/model-items', 'App\Http\Controllers\Admin\MediaController@modelItems');
    Route::get('/media/stats', 'App\Http\Controllers\Admin\MediaController@stats');
    Route::get('/media/contexts', 'App\Http\Controllers\Admin\MediaController@contexts');
    Route::get('/media/library', 'App\Http\Controllers\Admin\MediaController@libraryImages');
    Route::post('/media/upload', 'App\Http\Controllers\Admin\MediaController@upload');
    Route::get('/media/{media}', 'App\Http\Controllers\Admin\MediaController@show');
    Route::delete('/media/{media}', 'App\Http\Controllers\Admin\MediaController@destroy');
    Route::post('/media/bulk-delete', 'App\Http\Controllers\Admin\MediaController@bulkDestroy');

    // Migration Dashboard
    Route::get('/migrations', 'App\Http\Controllers\Admin\MigrationController@index');
    Route::post('/migrations/start', 'App\Http\Controllers\Admin\MigrationController@start');
    Route::get('/migrations/status/{batchId}', 'App\Http\Controllers\Admin\MigrationController@status');
    Route::get('/migrations/logs/{batchId}/{migrationKey}', 'App\Http\Controllers\Admin\MigrationController@logs');
    Route::post('/migrations/cancel/{batchId}', 'App\Http\Controllers\Admin\MigrationController@cancel');
    Route::get('/migrations/history', 'App\Http\Controllers\Admin\MigrationController@history');
    Route::delete('/migrations/history', 'App\Http\Controllers\Admin\MigrationController@clearHistory');

    // Translation Management
    Route::get('/translations/locales', 'App\Http\Controllers\Admin\TranslationController@locales');
    Route::get('/translations/js/{locale}', 'App\Http\Controllers\Admin\TranslationController@getJsTranslations');
    Route::put('/translations/js/{locale}', 'App\Http\Controllers\Admin\TranslationController@updateJsTranslations');
    Route::get('/translations/php/{locale}/{file?}', 'App\Http\Controllers\Admin\TranslationController@getPhpTranslations');
    Route::put('/translations/php/{locale}/{file}', 'App\Http\Controllers\Admin\TranslationController@updatePhpTranslations');
    Route::post('/translations/locales', 'App\Http\Controllers\Admin\TranslationController@createLocale');
    Route::post('/translations/keys', 'App\Http\Controllers\Admin\TranslationController@addKey');
    Route::delete('/translations/keys', 'App\Http\Controllers\Admin\TranslationController@deleteKey');
    Route::get('/translations/scan', 'App\Http\Controllers\Admin\TranslationController@scanMissing');
    Route::get('/translations/report', 'App\Http\Controllers\Admin\TranslationController@generateReport');
    Route::get('/translations/compare', 'App\Http\Controllers\Admin\TranslationController@compareLocales');

    // Model Translations Management
    Route::get('/model-translations', 'App\Http\Controllers\Admin\ModelTranslationController@index');
    Route::get('/model-translations/stats', 'App\Http\Controllers\Admin\ModelTranslationController@stats');
    Route::get('/model-translations/missing/{locale}', 'App\Http\Controllers\Admin\ModelTranslationController@missing');
    Route::get('/model-translations/{modelType}', 'App\Http\Controllers\Admin\ModelTranslationController@listItems');
    Route::get('/model-translations/{modelType}/{id}', 'App\Http\Controllers\Admin\ModelTranslationController@show');
    Route::put('/model-translations/{modelType}/{id}', 'App\Http\Controllers\Admin\ModelTranslationController@update');
    Route::put('/model-translations/{modelType}/bulk', 'App\Http\Controllers\Admin\ModelTranslationController@bulkUpdate');
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

    $user->update([
        'previous_login_at' => $user->last_login_at,
        'last_login_at' => now()->toDateTimeString(),
        'last_login_ip' => $request->getClientIp(),
    ]);

    $token = $user->createToken('my-app-token')->plainTextToken;

    $response = [
        'user'  => $user,
        'token' => $token,
    ];

    return response($response, 201);
});
