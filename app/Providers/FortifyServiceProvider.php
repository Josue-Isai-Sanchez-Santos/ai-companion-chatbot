<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap application services.
     */
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);

        Fortify::loginView(
            fn () => view('auth.login')
        );

        Fortify::registerView(
            fn () => view('auth.register')
        );

        RateLimiter::for('login', function (Request $request): Limit {
            $email = Str::transliterate(
                Str::lower((string) $request->input(Fortify::username()))
            );

            return Limit::perMinute(5)
                ->by($email.'|'.$request->ip());
        });
    }
}
