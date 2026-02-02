<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiKey
{
    /**
     * Handle an incoming request.
     *
     * Authenticates requests using API key in header:
     * X-API-Key: {key}
     * X-API-Secret: {secret}
     *
     * Or using Bearer token format:
     * Authorization: Bearer {key}:{secret}
     */
    public function handle(Request $request, Closure $next, ?string $ability = null): Response
    {
        // Check if API keys are enabled
        $apiKeysEnabled = Setting::get('api_keys_enabled', false);
        if (!$apiKeysEnabled) {
            return response()->json([
                'message' => 'API key authentication is not enabled.',
            ], 403);
        }

        // Try to extract API credentials
        $credentials = $this->extractCredentials($request);

        if (!$credentials) {
            return response()->json([
                'message' => 'API key authentication required.',
                'hint'    => 'Provide X-API-Key and X-API-Secret headers, or Authorization: Bearer {key}:{secret}',
            ], 401);
        }

        // Find the API key
        $apiKey = ApiKey::findByKey($credentials['key']);

        if (!$apiKey) {
            return response()->json([
                'message' => 'Invalid API key.',
            ], 401);
        }

        // Verify secret
        if (!$apiKey->verifySecret($credentials['secret'])) {
            return response()->json([
                'message' => 'Invalid API secret.',
            ], 401);
        }

        // Check if key is valid (active and not expired)
        if (!$apiKey->isValid()) {
            return response()->json([
                'message' => 'API key is inactive or expired.',
            ], 401);
        }

        // Check ability if required
        if ($ability && !$apiKey->hasAbility($ability)) {
            return response()->json([
                'message' => 'API key does not have the required ability: '.$ability,
            ], 403);
        }

        // Record usage
        $apiKey->recordUsage();

        // Attach API key and user to request
        $request->attributes->set('api_key', $apiKey);

        // Set the authenticated user from the API key
        auth()->setUser($apiKey->user);

        return $next($request);
    }

    /**
     * Extract API credentials from request.
     */
    protected function extractCredentials(Request $request): ?array
    {
        // Method 1: X-API-Key and X-API-Secret headers
        $key = $request->header('X-API-Key');
        $secret = $request->header('X-API-Secret');

        if ($key && $secret) {
            return ['key' => $key, 'secret' => $secret];
        }

        // Method 2: Authorization Bearer token (key:secret format)
        $authHeader = $request->header('Authorization');
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            $token = substr($authHeader, 7);
            $parts = explode(':', $token, 2);

            if (count($parts) === 2) {
                return ['key' => $parts[0], 'secret' => $parts[1]];
            }
        }

        return null;
    }
}
