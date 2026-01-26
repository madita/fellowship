<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <!-- SEO Meta Tags -->
        <title>{{ $seo['meta_title'] ?? $seo['app_name'] ?? 'Fellowship Community' }}</title>
        @if(!empty($seo['meta_description']))
        <meta name="description" content="{{ $seo['meta_description'] }}">
        @endif
        @if(!empty($seo['meta_keywords']))
        <meta name="keywords" content="{{ $seo['meta_keywords'] }}">
        @endif

        <!-- Robots -->
        @if(isset($seo['indexing_enabled']) && !$seo['indexing_enabled'])
        <meta name="robots" content="noindex, nofollow">
        @else
        <meta name="robots" content="index, follow">
        @endif

        <!-- Canonical URL -->
        @if(!empty($seo['canonical_url']))
        <link rel="canonical" href="{{ $seo['canonical_url'] }}">
        @endif

        <!-- Open Graph Meta Tags -->
        <meta property="og:type" content="website">
        <meta property="og:url" content="{{ url()->current() }}">
        <meta property="og:title" content="{{ $seo['og_title'] ?? $seo['meta_title'] ?? $seo['app_name'] ?? 'Fellowship Community' }}">
        @if(!empty($seo['og_description']) || !empty($seo['meta_description']))
        <meta property="og:description" content="{{ $seo['og_description'] ?? $seo['meta_description'] }}">
        @endif
        @if(!empty($seo['og_image']))
        <meta property="og:image" content="{{ str_starts_with($seo['og_image'], 'http') ? $seo['og_image'] : asset('storage/' . $seo['og_image']) }}">
        @elseif(!empty($seo['app_logo']))
        <meta property="og:image" content="{{ str_starts_with($seo['app_logo'], 'http') ? $seo['app_logo'] : asset('storage/' . $seo['app_logo']) }}">
        @endif
        <meta property="og:site_name" content="{{ $seo['app_name'] ?? 'Fellowship Community' }}">

        <!-- Twitter Card Meta Tags -->
        <meta name="twitter:card" content="{{ $seo['twitter_card_type'] ?? 'summary_large_image' }}">
        @if(!empty($seo['twitter_site']))
        <meta name="twitter:site" content="{{ $seo['twitter_site'] }}">
        @endif
        <meta name="twitter:title" content="{{ $seo['og_title'] ?? $seo['meta_title'] ?? $seo['app_name'] ?? 'Fellowship Community' }}">
        @if(!empty($seo['og_description']) || !empty($seo['meta_description']))
        <meta name="twitter:description" content="{{ $seo['og_description'] ?? $seo['meta_description'] }}">
        @endif
        @if(!empty($seo['og_image']))
        <meta name="twitter:image" content="{{ str_starts_with($seo['og_image'], 'http') ? $seo['og_image'] : asset('storage/' . $seo['og_image']) }}">
        @elseif(!empty($seo['app_logo']))
        <meta name="twitter:image" content="{{ str_starts_with($seo['app_logo'], 'http') ? $seo['app_logo'] : asset('storage/' . $seo['app_logo']) }}">
        @endif

        <!-- PWA Meta Tags -->
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <meta name="apple-mobile-web-app-title" content="{{ $seo['app_name'] ?? 'Fellowship' }}">
        <meta name="theme-color" content="#1976D2">

        <!-- Icons -->
        <link id="favicon" rel="icon" href="/favicon.ico">
        @if(file_exists(public_path('images/app-icon-192.png')))
        <link id="app-icon-192" rel="apple-touch-icon" sizes="192x192" href="/images/app-icon-192.png">
        @endif
        @if(file_exists(public_path('images/app-icon-512.png')))
        <link id="app-icon-512" rel="apple-touch-icon" sizes="512x512" href="/images/app-icon-512.png">
        @endif

        <!-- PWA Manifest -->
        <link rel="manifest" href="/manifest.json">

        <meta name="csrf-token" content="{{ csrf_token() }}">

        <!-- Google Fonts -->
        <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&family=Open+Sans:wght@300;400;600;700&family=Lato:wght@300;400;700&family=Montserrat:wght@300;400;600;700&family=Raleway:wght@300;400;600;700&family=Poppins:wght@300;400;600;700&family=Inter:wght@300;400;600;700&family=Source+Sans+Pro:wght@300;400;600;700&family=Quicksand:wght@300;400;500;600;700&display=swap" rel="stylesheet">

{{--        <link href="{{ mix('dist/css/app.css') }}" rel="stylesheet">--}}
        @vite(['resources/sass/app.scss', 'resources/js/app.js'])
    </head>
    <body>
        <noscript>
            <strong>We're sorry but this website doesn't work properly without JavaScript enabled. Please enable it to continue.</strong>
        </noscript>
        <div id="app"></div>
        <script>
            window.App = {!! json_encode([
                'csrfToken' => csrf_token(),
                'baseUrl' => url('/'),
                'routes' => collect(Route::getRoutes())->mapWithKeys(function ($route) { return [$route->getName() => $route->uri()]; })
            ]) !!};
        </script>
{{--        <script src="{{ mix('dist/js/app.js') }}"></script>--}}
    </body>
</html>
