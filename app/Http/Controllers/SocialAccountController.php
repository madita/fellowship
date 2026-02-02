<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;

class SocialAccountController extends Controller
{
    /**
     * Supported OAuth providers.
     */
    private const PROVIDERS = ['google', 'discord', 'github', 'facebook'];

    /**
     * Get list of user's connected social accounts.
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $accounts = $user->socialAccounts()->get()->map(function ($account) {
            return [
                'provider'     => $account->provider,
                'provider_id'  => $account->provider_id,
                'avatar'       => $account->avatar,
                'connected_at' => $account->created_at->diffForHumans(),
            ];
        });

        // Add available providers to link
        $availableProviders = collect(self::PROVIDERS)->filter(function ($provider) use ($user) {
            return !$user->hasSocialProvider($provider);
        })->values();

        return response()->json([
            'connected'    => $accounts,
            'available'    => $availableProviders,
            'has_password' => !is_null($user->password),
        ]);
    }

    /**
     * Disconnect (unlink) a social account from user.
     *
     * @param Request $request
     * @param string  $provider
     *
     * @return JsonResponse
     */
    public function disconnect(Request $request, $provider): JsonResponse
    {
        if (!in_array($provider, self::PROVIDERS)) {
            return response()->json(['error' => 'Invalid provider'], 400);
        }

        $user = $request->user();

        // Check if user has password or other social accounts
        $connectedAccounts = $user->socialAccounts()->count();
        $hasPassword = !is_null($user->password);

        if ($connectedAccounts === 1 && !$hasPassword) {
            return response()->json([
                'error' => 'Cannot disconnect last authentication method. Please set a password first.',
            ], 422);
        }

        // Delete the social account
        $deleted = $user->socialAccounts()->where('provider', $provider)->delete();

        if (!$deleted) {
            return response()->json(['error' => 'Provider not connected'], 404);
        }

        return response()->json([
            'message' => ucfirst($provider).' account disconnected successfully',
        ]);
    }

    /**
     * Link a new social provider to existing user account.
     *
     * @param Request $request
     * @param string  $provider
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function link(Request $request, $provider)
    {
        if (!in_array($provider, self::PROVIDERS)) {
            return redirect('/')->with('error', 'Invalid OAuth provider');
        }

        $user = $request->user();

        // Check if already linked
        if ($user->hasSocialProvider($provider)) {
            return redirect('/')->with('error', 'Provider already connected');
        }

        try {
            // Store user ID in session to link after OAuth callback
            session(['link_social_account' => $user->id]);

            return Socialite::driver($provider)->redirect();
        } catch (\Exception $e) {
            \Log::error('OAuth link error: '.$e->getMessage());

            return redirect('/')->with('error', 'OAuth configuration error');
        }
    }
}
