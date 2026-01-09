<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <!-- PWA Meta Tags -->
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <meta name="apple-mobile-web-app-title" content="Fellowship">
        <meta name="theme-color" content="#1976D2">

        <!-- Icons -->
        <link id="favicon" rel="icon" href="/favicon.ico">
        <link id="app-icon-192" rel="apple-touch-icon" sizes="192x192" href="/images/app-icon-192.png">
        <link id="app-icon-512" rel="apple-touch-icon" sizes="512x512" href="/images/app-icon-512.png">

        <!-- PWA Manifest -->
        <link rel="manifest" href="/manifest.json">

        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>Fellowship Community</title>

        <!-- Quicksand Font -->
        <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@300;400;500;600;700&display=swap" rel="stylesheet">

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
