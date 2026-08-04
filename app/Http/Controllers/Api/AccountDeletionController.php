<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AccountDeletionController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Check if right to be forgotten is enabled.
     */
    public function status(): JsonResponse
    {
        $enabled = (bool) Setting::get('right_to_be_forgotten_enabled', false);

        return response()->json([
            'enabled' => $enabled,
        ]);
    }

    /**
     * Export user data (GDPR data portability).
     */
    public function exportData(Request $request): JsonResponse
    {
        $user = $request->user();

        // Collect all user data
        $userData = [
            'account' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'created_at' => $user->created_at->toISOString(),
                'updated_at' => $user->updated_at->toISOString(),
                'email_verified_at' => $user->email_verified_at?->toISOString(),
            ],
            'profile' => [
                'avatar' => $user->avatar,
                'timezone' => $user->timezone,
                'date_format' => $user->date_format,
                'theme_mode' => $user->theme_mode,
                'language' => $user->language,
            ],
            'social_accounts' => $user->socialAccounts()->get()->map(function ($account) {
                return [
                    'provider' => $account->provider,
                    'created_at' => $account->created_at->toISOString(),
                ];
            })->toArray(),
            'api_keys' => $user->apiKeys()->get()->map(function ($key) {
                return [
                    'name' => $key->name,
                    'created_at' => $key->created_at->toISOString(),
                    'last_used_at' => $key->last_used_at?->toISOString(),
                    'request_count' => $key->request_count,
                ];
            })->toArray(),
            'export_date' => now()->toISOString(),
        ];

        // Add posts if the relationship exists
        if (method_exists($user, 'posts')) {
            $userData['posts'] = $user->posts()->get()->map(function ($post) {
                return [
                    'id' => $post->id,
                    'title' => $post->title,
                    'content' => $post->content,
                    'created_at' => $post->created_at->toISOString(),
                ];
            })->toArray();
        }

        // Add messages/conversations if the relationship exists
        if (method_exists($user, 'messages')) {
            $userData['messages'] = $user->messages()->get()->map(function ($message) {
                return [
                    'id' => $message->id,
                    'content' => $message->content ?? $message->body,
                    'created_at' => $message->created_at->toISOString(),
                ];
            })->toArray();
        }

        return response()->json([
            'message' => __('messages.account.data_exported'),
            'data' => $userData,
        ]);
    }

    /**
     * Request account deletion.
     */
    public function requestDeletion(Request $request): JsonResponse
    {
        // Check if feature is enabled
        $enabled = (bool) Setting::get('right_to_be_forgotten_enabled', false);
        if (! $enabled) {
            return response()->json([
                'message' => __('messages.account.deletion_disabled'),
            ], 403);
        }

        $request->validate([
            'password' => 'required|string',
            'reason' => 'nullable|string|max:1000',
            'confirm' => 'required|accepted',
        ]);

        $user = $request->user();

        // Verify password
        if (! Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => __('messages.account.incorrect_password'),
                'errors' => ['password' => [__('messages.account.password_incorrect')]],
            ], 422);
        }

        // Log the deletion request
        Log::info('Account deletion requested', [
            'user_id' => $user->id,
            'email' => $user->email,
            'reason' => $request->reason,
            'ip' => $request->ip(),
        ]);

        // Send notification to admin
        $adminEmail = Setting::get('admin_email');
        if ($adminEmail) {
            try {
                Mail::raw(
                    "User account deletion requested:\n\n".
                    "User ID: {$user->id}\n".
                    "Username: {$user->username}\n".
                    "Email: {$user->email}\n".
                    'Reason: '.($request->reason ?: 'Not provided')."\n".
                    'Requested at: '.now()->toISOString(),
                    function ($message) use ($adminEmail, $user) {
                        $message->to($adminEmail)
                            ->subject(__('messages.account.deletion_subject', ['username' => $user->username]));
                    }
                );
            } catch (\Exception $e) {
                Log::warning('Failed to send admin notification for account deletion', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Perform the deletion
        $this->deleteUserData($user);

        return response()->json([
            'message' => __('messages.account.deleted'),
        ]);
    }

    /**
     * Delete all user data.
     */
    protected function deleteUserData(User $user): void
    {
        // Delete API keys
        $user->apiKeys()->delete();

        // Delete social accounts
        $user->socialAccounts()->delete();

        // Delete user's media files
        if (method_exists($user, 'media')) {
            $user->clearMediaCollection();
        }

        // Delete posts if exists
        if (method_exists($user, 'posts')) {
            $user->posts()->delete();
        }

        // Delete messages if exists
        if (method_exists($user, 'messages')) {
            $user->messages()->delete();
        }

        // Delete events if exists
        if (method_exists($user, 'events')) {
            $user->events()->delete();
        }

        // Revoke all tokens
        $user->tokens()->delete();

        // Finally, delete the user
        $user->delete();
    }
}
