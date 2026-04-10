<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

class ConditionalStartSession extends StartSession
{
    /**
     * Cookie name that indicates consent was given.
     */
    protected const CONSENT_COOKIE = 'cookie_consent_given';

    /**
     * Track whether we should save the session cookie.
     */
    protected bool $shouldSaveCookie = true;

    /**
     * Handle an incoming request.
     */
    public function handle($request, Closure $next)
    {
        // Determine if we should save the session cookie
        $this->shouldSaveCookie = $this->shouldSaveSessionCookie($request);

        // Always call parent to start the session (needed for CSRF, etc.)
        return parent::handle($request, $next);
    }

    /**
     * Add the session cookie to the response.
     * Override to conditionally prevent cookie from being set.
     */
    protected function addCookieToResponse(Response $response, Session $session)
    {
        // Only add the session cookie if consent was given
        if ($this->shouldSaveCookie) {
            return parent::addCookieToResponse($response, $session);
        }

        // No consent - don't add session cookie, but still save session data
        // This allows the session to work for the current request only
    }

    /**
     * Determine if we should save the session cookie.
     */
    protected function shouldSaveSessionCookie(Request $request): bool
    {
        // If cookie consent feature is disabled, always save
        if (!$this->isCookieConsentEnabled()) {
            return true;
        }

        // If user has given consent, save
        if ($this->hasConsent($request)) {
            return true;
        }

        // No consent - don't save session cookie
        return false;
    }

    /**
     * Check if user has given cookie consent.
     */
    protected function hasConsent(Request $request): bool
    {
        return $request->cookie(self::CONSENT_COOKIE) === 'true';
    }

    /**
     * Check if cookie consent feature is enabled in settings.
     */
    protected function isCookieConsentEnabled(): bool
    {
        try {
            // Check setting from database/cache
            $setting = \App\Models\Setting::get('cookie_consent_enabled', true);

            return (bool) $setting;
        } catch (\Exception $e) {
            // If we can't check settings, assume consent is not required
            return false;
        }
    }
}
