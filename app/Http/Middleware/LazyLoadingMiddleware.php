<?php

namespace App\Http\Middleware;

use App\Services\LazyLoadingService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LazyLoadingMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only process HTML responses
        if (! $this->isHtmlResponse($response)) {
            return $response;
        }

        // Check if lazy loading is enabled
        if (! LazyLoadingService::isEnabled()) {
            return $response;
        }

        // Get the content
        $content = $response->getContent();

        // Process images and iframes
        $content = LazyLoadingService::processAllElements($content);

        // Set the modified content
        $response->setContent($content);

        return $response;
    }

    /**
     * Check if the response is an HTML response.
     */
    protected function isHtmlResponse(Response $response): bool
    {
        $contentType = $response->headers->get('Content-Type', '');

        return str_contains($contentType, 'text/html');
    }
}
