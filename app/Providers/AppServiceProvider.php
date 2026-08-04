<?php

namespace App\Providers;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Schema::defaultStringLength(191);

        // snippet from https://gist.github.com/simonhamp/549e8821946e2c40a617c85d2cf5af5e
        Collection::macro('paginate', function ($perPage, $total = null, $page = null, $pageName = 'page') {
            $page = $page ?: LengthAwarePaginator::resolveCurrentPage($pageName);

            return new LengthAwarePaginator(
                $this->forPage($page, $perPage)->values(),
                $total ?: $this->count(),
                $perPage,
                $page,
                [
                    'path' => LengthAwarePaginator::resolveCurrentPath(),
                    'pageName' => $pageName,
                ]
            );
        });

        $this->registerBladeDirectives();
    }

    /**
     * Register custom Blade directives.
     */
    protected function registerBladeDirectives(): void
    {
        // @lazyImage($src, $alt, $class)
        // Outputs an img tag with lazy loading based on settings
        Blade::directive('lazyImage', function ($expression) {
            return "<?php
                \$args = [$expression];
                \$src = \$args[0] ?? '';
                \$alt = \$args[1] ?? '';
                \$class = \$args[2] ?? '';
                \$loading = \\App\\Services\\LazyLoadingService::isEnabled() ? 'lazy' : 'eager';
                echo '<img src=\"' . e(\$src) . '\" alt=\"' . e(\$alt) . '\" class=\"' . e(\$class) . '\" loading=\"' . \$loading . '\">';
            ?>";
        });

        // @lazyLoadingEnabled
        // Outputs true/false based on setting
        Blade::directive('lazyLoadingEnabled', function () {
            return "<?php echo \\App\\Services\\LazyLoadingService::isEnabled() ? 'true' : 'false'; ?>";
        });

        // @imageOptimizationEnabled
        // Outputs true/false based on setting
        Blade::directive('imageOptimizationEnabled', function () {
            return "<?php echo \\App\\Services\\ImageOptimizationService::isEnabled() ? 'true' : 'false'; ?>";
        });
    }
}
