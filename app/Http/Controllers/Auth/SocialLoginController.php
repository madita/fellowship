<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\SocialAccount;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class SocialLoginController extends Controller
{
    /**
     * Supported OAuth providers
     */
    private const PROVIDERS = ['google', 'discord', 'github', 'facebook'];

    /**
     * Redirect the user to the OAuth Provider.
     *
     * @param string $provider
     * @return \Illuminate\Http\RedirectResponse
     */
    public function redirect($provider)
    {
        // Validate provider
        if (!in_array($provider, self::PROVIDERS)) {
            return redirect('/auth/signin')->with('error', 'Invalid OAuth provider');
        }

        // Check if the provider is enabled
        $enabled = Setting::get("oauth_{$provider}_enabled", false);
        if (!$enabled) {
            return redirect('/auth/signin')->with('error', ucfirst($provider) . ' login is currently disabled');
        }

        try {
            $driver = Socialite::driver($provider);

            // Add provider-specific scopes
            if ($provider === 'discord') {
                $driver->scopes(['identify', 'email']);
            } elseif ($provider === 'github') {
                $driver->scopes(['user:email']);
            }

            return $driver->redirect();
        } catch (\Exception $e) {
            \Log::error('OAuth redirect error: ' . $e->getMessage());
            return redirect('/auth/signin')->with('error', 'OAuth configuration error. Please contact administrator.');
        }
    }

    /**
     * Obtain the user information from the OAuth Provider.
     *
     * @param string $provider
     * @return \Illuminate\Http\RedirectResponse
     */
    public function callback($provider)
    {

        // Validate provider
        if (!in_array($provider, self::PROVIDERS)) {
            return redirect('/auth/signin')->with('error', 'Invalid OAuth provider');
        }

        // Log any error from the OAuth provider
        if (request()->has('error')) {
            \Log::error('OAuth error from ' . $provider . ': ' . request()->get('error') . ' - ' . request()->get('error_description'));
            return redirect('/auth/signin')->with('error', 'OAuth error: ' . request()->get('error_description', request()->get('error')));
        }

        // Check if the provider is enabled
        $enabled = Setting::get("oauth_{$provider}_enabled", false);
        if (!$enabled) {
            return redirect('/auth/signin')->with('error', ucfirst($provider) . ' login is currently disabled');
        }

        try {
            // Get OAuth user data from provider
            $driver = Socialite::driver($provider);

            // Add scopes for callback as well (needed for stateless)
            if ($provider === 'discord') {
                $driver->scopes(['identify', 'email']);
            } elseif ($provider === 'github') {
                $driver->scopes(['user:email']);
            }

            $oauthUser = $driver->user();

            // Validate that we have an email (required for account creation)
            if (!$oauthUser->getEmail()) {
                \Log::error('OAuth callback: No email provided by ' . $provider . ' for user ' . $oauthUser->getId());
                return redirect('/auth/signin')->with('error', 'Unable to get email from ' . ucfirst($provider) . '. Please ensure your email is public or try another login method.');
            }

            // Find or create user and social account
            $user = $this->findOrCreateUser($oauthUser, $provider);

            // Log the user in
            Auth::login($user, true);

            // Update last login info
            $user->update([
                'last_login_at' => Carbon::now()->toDateTimeString(),
                'last_login_ip' => request()->getClientIp(),
            ]);

            // Create Sanctum token for API authentication
            $token = $user->createToken('oauth-login')->plainTextToken;

            // Redirect to frontend with token (frontend will handle auth state sync)
            return redirect('/?token=' . $token);

        } catch (\Exception $e) {

            \Log::error('OAuth callback error for ' . $provider . ': ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return redirect('/auth/signin')->with('error', 'Authentication failed. Please try again.');
        }
    }

    /**
     * Find existing user or create new user from OAuth data.
     *
     * @param \Laravel\Socialite\Contracts\User $oauthUser
     * @param string $provider
     * @return User
     */
    private function findOrCreateUser($oauthUser, $provider)
    {
        // Check if social account exists
        $socialAccount = SocialAccount::where('provider', $provider)
            ->where('provider_id', $oauthUser->getId())
            ->first();

        if ($socialAccount) {
            // Update token and avatar
            $socialAccount->update([
                'provider_token' => $oauthUser->token,
                'provider_refresh_token' => $oauthUser->refreshToken ?? null,
                'avatar' => $oauthUser->getAvatar(),
            ]);

            return $socialAccount->user;
        }

        // Check if user exists with same email
        $user = User::where('email', $oauthUser->getEmail())->first();

        if (!$user) {
            // Check if new user registration via OAuth is allowed
            $allowRegistration = Setting::get('oauth_allow_registration', true);
            if (!$allowRegistration) {
                throw new \Exception('New user registration via OAuth is currently disabled. Please create an account first or contact an administrator.');
            }

            // Check if email auto-verification is enabled
            $autoVerifyEmail = Setting::get('oauth_auto_verify_email', true);

            // Create the new user
            $user = User::create([
                'name' => $oauthUser->getName() ?? $oauthUser->getNickname(),
                'username' => $this->generateUniqueUsername($oauthUser),
                'email' => $oauthUser->getEmail(),
                'password' => null, // OAuth users don't need password
                'email_verified_at' => $autoVerifyEmail ? now() : null,
            ]);
        }

        // Create the social account link
        $user->socialAccounts()->create([
            'provider' => $provider,
            'provider_id' => $oauthUser->getId(),
            'provider_token' => $oauthUser->token,
            'provider_refresh_token' => $oauthUser->refreshToken ?? null,
            'avatar' => $oauthUser->getAvatar(),
        ]);

        return $user;
    }

    /**
     * Generate a unique username from OAuth user data.
     *
     * @param \Laravel\Socialite\Contracts\User $oauthUser
     * @return string
     */
    private function generateUniqueUsername($oauthUser)
    {
        // Try the nickname first
        $username = $oauthUser->getNickname();

        // If no nickname, generate from name or email
        if (!$username) {
            $username = $oauthUser->getName()
                ? Str::slug($oauthUser->getName(), '_')
                : explode('@', $oauthUser->getEmail())[0];
        }

        // Remove special characters
        $username = preg_replace('/[^a-zA-Z0-9_]/', '', $username);

        // Ensure minimum length
        if (strlen($username) < 3) {
            $username = 'user' . $username;
        }

        // Make unique by appending numbers if needed
        $originalUsername = $username;
        $counter = 1;

        while (User::where('username', $username)->exists()) {
            $username = $originalUsername . $counter;
            $counter++;
        }

        return strtolower($username);
    }
}
