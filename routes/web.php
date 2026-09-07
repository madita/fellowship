<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

// Route::get('/', function () {
//     return view('welcome');
// });
//Auth::routes(['verify' => true]);
Auth::routes(['verify' => true]);

// OAuth Social Login Routes
Route::get('/auth/{provider}', [App\Http\Controllers\Auth\SocialLoginController::class, 'redirect'])
    ->name('social.redirect')
    ->where('provider', 'google|discord|github|facebook');

Route::get('/auth/{provider}/callback', [App\Http\Controllers\Auth\SocialLoginController::class, 'callback'])
    ->name('social.callback')
    ->where('provider', 'google|discord|github|facebook');

Route::get('/tag/taxonomies', '\App\Http\Controllers\TaxonomyController@getTaxonomies');
Route::get('/tag/terms/{taxonomy?}', '\App\Http\Controllers\TaxonomyController@getTerms');
Route::get('/taxables', '\App\Http\Controllers\TaxonomyController@getTaxables');
Route::post('/tag/terms/', '\App\Http\Controllers\TaxonomyController@saveTerms');

//
//Route::group(['prefix' => '/api'], function () {
//    //    Route::get('/', 'App\Http\Controllers\Chat\ChatController@index')->name('chat');
//    Route::get('/pages/{slug}', '\App\Http\Controllers\PageController@view');
//    Route::get('/pages/{page}/history', '\App\Http\Controllers\PageController@history');
//    //Route::get('/pages/tag/{term}', '\App\Http\Controllers\PageController@showWithTerm');
//    Route::get('/pages/{taxonomy}/{category}', '\App\Http\Controllers\PageController@showWithCategory');
//    Route::get('/posts/{slug}', '\App\Http\Controllers\PostController@view');
//});
//

//migration — controller intentionally not in the repo yet
if (class_exists(\App\Http\Controllers\Admin\MigrationController::class)) {
    Route::get('migration/wiki', "\App\Http\Controllers\Admin\MigrationController@wiki");
    Route::get('migration/event', "\App\Http\Controllers\Admin\MigrationController@event");
}

// PWA Manifest
Route::get('/manifest.json', 'App\Http\Controllers\ManifestController@generate')->name('manifest');

// SEO Routes - Sitemap and Robots.txt
Route::get('/sitemap_index.xml', 'App\Http\Controllers\SitemapController@sitemapIndex')->name('sitemap.index');
Route::get('/sitemap.xml', 'App\Http\Controllers\SitemapController@index')->name('sitemap');
Route::get('/sitemap-wiki.xml', 'App\Http\Controllers\SitemapController@wikiSitemap')->name('sitemap.wiki');
Route::get('/sitemap-pages.xml', 'App\Http\Controllers\SitemapController@pagesSitemap')->name('sitemap.pages');
Route::get('/sitemap-posts.xml', 'App\Http\Controllers\SitemapController@postsSitemap')->name('sitemap.posts');
Route::get('/robots.txt', 'App\Http\Controllers\SitemapController@robots')->name('robots');

// SPA Catch-all - MUST BE LAST and exclude actual files
Route::get('/{any}', 'App\Http\Controllers\SpaController@index')
    ->where('any', '^(?!.*(\.js|\.css|\.json|\.woff|\.woff2|\.ttf|\.eot|\.svg|\.png|\.jpg|\.jpeg|\.gif|\.ico|\.webp)$).*')
    ->name('spa');
