<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ApiKeyController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Check if API keys feature is enabled
     */
    public function status(): JsonResponse
    {
        $enabled = (bool) Setting::get('api_keys_enabled', false);

        return response()->json([
            'enabled' => $enabled,
            'rate_limit' => (int) Setting::get('api_rate_limit_per_minute', 60),
        ]);
    }

    /**
     * List user's API keys
     */
    public function index(Request $request): JsonResponse
    {
        $this->checkApiKeysEnabled();

        $keys = $request->user()
            ->apiKeys()
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($key) {
                return [
                    'id' => $key->id,
                    'name' => $key->name,
                    'key' => $key->key,
                    'key_preview' => substr($key->key, 0, 12) . '...',
                    'abilities' => $key->abilities,
                    'is_active' => $key->is_active,
                    'last_used_at' => $key->last_used_at?->toISOString(),
                    'expires_at' => $key->expires_at?->toISOString(),
                    'request_count' => $key->request_count,
                    'created_at' => $key->created_at->toISOString(),
                ];
            });

        return response()->json([
            'api_keys' => $keys,
        ]);
    }

    /**
     * Create a new API key
     */
    public function store(Request $request): JsonResponse
    {
        $this->checkApiKeysEnabled();

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'abilities' => 'nullable|array',
            'abilities.*' => 'string',
            'expires_at' => 'nullable|date|after:now',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Limit number of API keys per user
        $maxKeys = 10;
        if ($request->user()->apiKeys()->count() >= $maxKeys) {
            return response()->json([
                'message' => "You can only have up to {$maxKeys} API keys.",
            ], 422);
        }

        // Generate key pair
        $keyPair = ApiKey::generateKeyPair();

        // Create the API key
        $apiKey = $request->user()->apiKeys()->create([
            'name' => $request->name,
            'key' => $keyPair['key'],
            'secret_hash' => $keyPair['secret_hash'],
            'abilities' => $request->abilities,
            'expires_at' => $request->expires_at,
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'API key created successfully. Save the secret - it will not be shown again!',
            'api_key' => [
                'id' => $apiKey->id,
                'name' => $apiKey->name,
                'key' => $keyPair['key'],
                'secret' => $keyPair['secret'], // Only shown once!
                'abilities' => $apiKey->abilities,
                'expires_at' => $apiKey->expires_at?->toISOString(),
                'created_at' => $apiKey->created_at->toISOString(),
            ],
        ], 201);
    }

    /**
     * Get a specific API key
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $this->checkApiKeysEnabled();

        $apiKey = $request->user()->apiKeys()->findOrFail($id);

        return response()->json([
            'api_key' => [
                'id' => $apiKey->id,
                'name' => $apiKey->name,
                'key' => $apiKey->key,
                'abilities' => $apiKey->abilities,
                'is_active' => $apiKey->is_active,
                'last_used_at' => $apiKey->last_used_at?->toISOString(),
                'expires_at' => $apiKey->expires_at?->toISOString(),
                'request_count' => $apiKey->request_count,
                'created_at' => $apiKey->created_at->toISOString(),
            ],
        ]);
    }

    /**
     * Update an API key
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $this->checkApiKeysEnabled();

        $apiKey = $request->user()->apiKeys()->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'abilities' => 'nullable|array',
            'abilities.*' => 'string',
            'is_active' => 'sometimes|boolean',
            'expires_at' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $apiKey->update($validator->validated());

        return response()->json([
            'message' => 'API key updated successfully',
            'api_key' => [
                'id' => $apiKey->id,
                'name' => $apiKey->name,
                'key' => $apiKey->key,
                'abilities' => $apiKey->abilities,
                'is_active' => $apiKey->is_active,
                'expires_at' => $apiKey->expires_at?->toISOString(),
            ],
        ]);
    }

    /**
     * Delete an API key
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->checkApiKeysEnabled();

        $apiKey = $request->user()->apiKeys()->findOrFail($id);
        $apiKey->delete();

        return response()->json([
            'message' => 'API key deleted successfully',
        ]);
    }

    /**
     * Regenerate an API key's secret
     */
    public function regenerate(Request $request, int $id): JsonResponse
    {
        $this->checkApiKeysEnabled();

        $apiKey = $request->user()->apiKeys()->findOrFail($id);

        // Generate new key pair
        $keyPair = ApiKey::generateKeyPair();

        $apiKey->update([
            'key' => $keyPair['key'],
            'secret_hash' => $keyPair['secret_hash'],
        ]);

        return response()->json([
            'message' => 'API key regenerated successfully. Save the new secret - it will not be shown again!',
            'api_key' => [
                'id' => $apiKey->id,
                'name' => $apiKey->name,
                'key' => $keyPair['key'],
                'secret' => $keyPair['secret'], // Only shown once!
            ],
        ]);
    }

    /**
     * Check if API keys feature is enabled
     */
    protected function checkApiKeysEnabled(): void
    {
        $enabled = (bool) Setting::get('api_keys_enabled', false);

        if (!$enabled) {
            abort(403, 'API keys feature is not enabled.');
        }
    }
}
