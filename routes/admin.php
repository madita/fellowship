<?php

use Illuminate\Support\Facades\Route;

Route::model('pages', '\App\Models\Page');
Route::resource('/pages', 'App\Http\Controllers\Admin\PageController');
