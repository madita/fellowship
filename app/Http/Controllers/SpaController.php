<?php

namespace App\Http\Controllers;

use App\Models\Setting;

class SpaController extends Controller
{
    public function index()
    {
        // Fetch SEO settings for meta tags
        $seo = [
            'app_name' => Setting::get('app_name', config('app.name', 'Fellowship')),
            'meta_title' => Setting::get('meta_title'),
            'meta_description' => Setting::get('meta_description'),
            'meta_keywords' => Setting::get('meta_keywords'),
            'og_title' => Setting::get('og_title'),
            'og_description' => Setting::get('og_description'),
            'og_image' => Setting::get('og_image'),
            'twitter_card_type' => Setting::get('twitter_card_type', 'summary_large_image'),
            'twitter_site' => Setting::get('twitter_site'),
            'canonical_url' => Setting::get('canonical_url'),
            'indexing_enabled' => Setting::get('indexing_enabled', true),
            'app_logo' => Setting::get('app_logo'),
        ];

        return view('spa', compact('seo'));
    }
}
