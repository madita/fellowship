<?php

namespace App\Providers;

use App\Models\Collection;
use App\Models\Irc\IrcConnection;
use App\Policies\CollectionPolicy;
use App\Policies\IrcConnectionPolicy;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use App\Support\FeaturePermissions;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        IrcConnection::class => IrcConnectionPolicy::class,
        Collection::class    => CollectionPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        Gate::define('admin', function ($user) {
            return $user->isAdmin();
        });

        // Every feature permission is an ability as well, so a
        // controller can ask for it with authorize() the way a route
        // asks with permission.any - and get the same answer. Admins
        // hold all of them; the rest is what the member was given.
        foreach (FeaturePermissions::names() as $permission) {
            Gate::define($permission, function ($user) use ($permission) {
                if ($user->isAdmin()) {
                    return true;
                }

                // By name, not hasPermissionTo: that throws when the
                // permission has not been seeded yet, and a missing row
                // should refuse the request, not break it
                return $user->getAllPermissions()->pluck('name')->contains($permission);
            });
        }

        ResetPassword::createUrlUsing(function ($user, string $token) {
            return env('APP_URL') . '/auth/reset-password/' . $token . '?email=' . urlencode($user->email);
        });
    }
}
