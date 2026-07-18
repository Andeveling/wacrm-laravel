<?php

namespace App\Providers;

use App\Auth\ApiKeyGuard;
use App\Auth\ApiKeyUserProvider;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the `api_key` guard as a custom driver.
 *
 * Lives in its own provider (not AppServiceProvider) so the auth wiring is
 * isolated from defaults like Carbon / Password rules. The guard relies on a
 * UserProvider that knows how to map a `key_hash` credential to an ApiKey —
 * registered against the same `users` provider slot as the web guard, but the
 * lookup ignores the User model and resolves ApiKey directly.
 */
class AuthServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Auth::provider('api-key', function () {
            return new ApiKeyUserProvider;
        });

        Auth::extend('custom-apikey', function (Application $app, string $name, array $config) {
            return new ApiKeyGuard(
                $app->make('auth')->createUserProvider($config['provider']),
                $app->make(Request::class),
            );
        });
    }
}
