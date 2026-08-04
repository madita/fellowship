<?php

namespace App\Providers;

use App\Models\Collection;
use App\Models\Irc\IrcConnection;
use App\Policies\CollectionPolicy;
use App\Policies\IrcConnectionPolicy;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
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
        Collection::class => CollectionPolicy::class,
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

        ResetPassword::createUrlUsing(function ($user, string $token) {
            return env('APP_URL').'/auth/reset-password/'.$token.'?email='.urlencode($user->email);
        });
    }
}
