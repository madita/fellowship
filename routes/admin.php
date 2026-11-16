<?php

use Illuminate\Support\Facades\Route;

// Route::model('pages', '\App\Models\Page');

// Behind the permission named after the feature, like the rest of the
// admin routes - see App\Support\FeaturePermissions.
Route::middleware('permission.any:manage-announcement')->group(function () {
    Route::resource('/announcement', 'App\Http\Controllers\Admin\AnnouncementController');
});
// Route::resource('/pages', 'App\Http\Controllers\Admin\PageController');
