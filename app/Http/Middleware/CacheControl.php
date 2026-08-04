<?php

namespace App\Http\Middleware;

use App\Services\CacheService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class CacheControl
{
    /**
     * Routes that should never be cached.
     */
    protected array $noCachePatterns = [
        'api/admin/*',
        'api/user/*',
        'api/auth/*',
        'admin/*',
        'login',
        'logout',
        'register',
        'sanctum/*',
        'broadcasting/*',
    ];

    /**
     * Routes that should be cached with their tags.
     */
    protected array $cacheableRoutes = [
        'api/wiki/*' => 'wiki',
        'api/pages/*' => 'pages',
        'api/posts/*' => 'posts',
        'api/homepage/*' => 'widgets',
        'api/settings/public' => 'settings',
        'api/footer/*' => 'settings',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ?string $ttl = null): Response
    {
        // Check if OpenSSL is available (required for Laravel's encryption)
        if (! function_exists('openssl_cipher_iv_length')) {
            Log::warning('CacheControl middleware skipped: OpenSSL extension is not available');

            return $next($request)->header('X-Cache-Status', 'openssl-missing');
        }

        // Only cache GET and HEAD requests
        if (! in_array($request->method(), ['GET', 'HEAD'])) {
            $response = $this->handleMutatingRequest($request, $next);

            return $response->header('X-Cache-Middleware', 'active');
        }

        try {
            // Check if caching is enabled
            if (! CacheService::isEnabled()) {
                $response = $next($request);

                return $this->addNoCacheHeaders($response)
                    ->header('X-Cache-Status', 'disabled')
                    ->header('X-Cache-Middleware', 'active');
            }

            // Don't cache if user is authenticated (personalized content)
            if ($request->user()) {
                $response = $next($request);

                return $this->addNoCacheHeaders($response)
                    ->header('X-Cache-Status', 'authenticated')
                    ->header('X-Cache-Middleware', 'active');
            }

            // Check if this route should not be cached
            if ($this->shouldNotCache($request)) {
                $response = $next($request);

                return $this->addNoCacheHeaders($response)
                    ->header('X-Cache-Status', 'excluded')
                    ->header('X-Cache-Middleware', 'active');
            }

            // Try to serve from cache
            return $this->handleCachedRequest($request, $next, $ttl);
        } catch (\Exception $e) {
            // If caching fails, just return the response without caching
            Log::warning('CacheControl middleware error: '.$e->getMessage());
            $response = $next($request);

            return $response
                ->header('X-Cache-Status', 'error: '.$e->getMessage())
                ->header('X-Cache-Middleware', 'active');
        }
    }

    /**
     * Handle a cacheable request.
     */
    protected function handleCachedRequest(Request $request, Closure $next, ?string $ttl): Response
    {
        $cacheKey = $this->getCacheKey($request);
        $tags = $this->getTagsForRoute($request);
        $lifetime = $ttl ? (int) $ttl * 60 : CacheService::getLifetime();

        // Try to get from cache
        $cached = CacheService::get($cacheKey, null, $tags);

        if ($cached !== null) {
            $response = response($cached['content'], $cached['status']);
            foreach ($cached['headers'] as $key => $value) {
                $response->header($key, $value);
            }

            return $response
                ->header('X-Cache', 'HIT')
                ->header('X-Cache-Key', $cacheKey)
                ->header('X-Cache-Status', 'cached')
                ->header('X-Cache-Middleware', 'active');
        }

        // Get fresh response
        $response = $next($request);

        // Only cache successful responses
        if ($response->isSuccessful()) {
            $cacheData = [
                'content' => $response->getContent(),
                'status' => $response->getStatusCode(),
                'headers' => $this->getCacheableHeaders($response),
            ];

            CacheService::put($cacheKey, $cacheData, $lifetime, $tags);
        }

        return $this->addCacheHeaders($response)
            ->header('X-Cache', 'MISS')
            ->header('X-Cache-Key', $cacheKey)
            ->header('X-Cache-Status', 'fresh')
            ->header('X-Cache-Middleware', 'active');
    }

    /**
     * Handle mutating requests (POST, PUT, DELETE, etc.)
     * These may invalidate cache.
     */
    protected function handleMutatingRequest(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // If request was successful, clear relevant cache
        if ($response->isSuccessful()) {
            $tags = $this->getTagsForRoute($request);
            if (! empty($tags)) {
                CacheService::flushTags($tags);
            }
        }

        return $response;
    }

    /**
     * Get cache tags for a route.
     */
    protected function getTagsForRoute(Request $request): array
    {
        $path = $request->path();

        foreach ($this->cacheableRoutes as $pattern => $tag) {
            if ($request->is($pattern)) {
                return [$tag];
            }
        }

        return [];
    }

    /**
     * Generate cache key for request.
     */
    protected function getCacheKey(Request $request): string
    {
        $url = $request->fullUrl();
        $locale = app()->getLocale();

        return 'http_cache:'.md5($url.':'.$locale);
    }

    /**
     * Get headers that should be cached.
     */
    protected function getCacheableHeaders(Response $response): array
    {
        return [
            'Content-Type' => $response->headers->get('Content-Type'),
        ];
    }

    /**
     * Check if request should not be cached.
     */
    protected function shouldNotCache(Request $request): bool
    {
        // Check against no-cache patterns
        foreach ($this->noCachePatterns as $pattern) {
            if ($request->is($pattern)) {
                return true;
            }
        }

        // Don't cache if request has cache-busting query params
        if ($request->has(['_', 'nocache', 'refresh'])) {
            return true;
        }

        // Don't cache if Cache-Control: no-cache header is present
        if ($request->header('Cache-Control') === 'no-cache') {
            return true;
        }

        return false;
    }

    /**
     * Add cache headers to response.
     */
    protected function addCacheHeaders(Response $response): Response
    {
        $lifetime = CacheService::getLifetime();

        // Remove any existing cache headers first
        $response->headers->remove('Cache-Control');
        $response->headers->remove('Pragma');
        $response->headers->remove('Expires');

        $response->headers->set('Cache-Control', "public, max-age={$lifetime}, s-maxage={$lifetime}");
        $response->headers->set('Pragma', 'cache');
        $response->headers->set('Expires', gmdate('D, d M Y H:i:s', time() + $lifetime).' GMT');

        return $response;
    }

    /**
     * Add no-cache headers to response.
     */
    protected function addNoCacheHeaders(Response $response): Response
    {
        // Remove any existing cache headers first
        $response->headers->remove('Cache-Control');
        $response->headers->remove('Pragma');
        $response->headers->remove('Expires');

        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, private');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }
}
