<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DynamicRateLimit
{
    protected RateLimiter $limiter;

    public function __construct(RateLimiter $limiter)
    {
        $this->limiter = $limiter;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $keyPrefix = 'api'): Response
    {
        // Get rate limit from settings (default: 60 per minute)
        $maxAttempts = (int) Setting::get('api_rate_limit_per_minute', 60);

        // Build the rate limiter key
        $key = $this->resolveRequestSignature($request, $keyPrefix);

        // Check if too many attempts
        if ($this->limiter->tooManyAttempts($key, $maxAttempts)) {
            return $this->buildTooManyAttemptsResponse($key, $maxAttempts);
        }

        // Hit the rate limiter
        $this->limiter->hit($key, 60); // 60 seconds decay

        $response = $next($request);

        // Add rate limit headers to response
        return $this->addHeaders(
            $response,
            $maxAttempts,
            $this->calculateRemainingAttempts($key, $maxAttempts)
        );
    }

    /**
     * Resolve the request signature for rate limiting.
     */
    protected function resolveRequestSignature(Request $request, string $prefix): string
    {
        // Use API key if present, otherwise use user ID or IP
        if ($apiKey = $request->attributes->get('api_key')) {
            return $prefix.':'.$apiKey->id;
        }

        if ($user = $request->user()) {
            return $prefix.':user:'.$user->id;
        }

        return $prefix.':ip:'.$request->ip();
    }

    /**
     * Calculate remaining attempts.
     */
    protected function calculateRemainingAttempts(string $key, int $maxAttempts): int
    {
        return $this->limiter->remaining($key, $maxAttempts);
    }

    /**
     * Build response for too many attempts.
     */
    protected function buildTooManyAttemptsResponse(string $key, int $maxAttempts): Response
    {
        $retryAfter = $this->limiter->availableIn($key);

        return response()->json([
            'message'     => __('messages.error.too_many_requests'),
            'retry_after' => $retryAfter,
        ], 429)->withHeaders([
            'X-RateLimit-Limit'     => $maxAttempts,
            'X-RateLimit-Remaining' => 0,
            'Retry-After'           => $retryAfter,
            'X-RateLimit-Reset'     => time() + $retryAfter,
        ]);
    }

    /**
     * Add rate limit headers to response.
     */
    protected function addHeaders(Response $response, int $maxAttempts, int $remainingAttempts): Response
    {
        $response->headers->add([
            'X-RateLimit-Limit'     => $maxAttempts,
            'X-RateLimit-Remaining' => max(0, $remainingAttempts),
        ]);

        return $response;
    }
}
