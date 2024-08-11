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

//migration
Route::get('migration/wiki', "\App\Http\Controllers\Admin\MigrationController@wiki");

Route::get('/{any}', 'App\Http\Controllers\SpaController@index')->where('any', '.*');
