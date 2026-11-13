<?php

namespace App\Providers;


use Illuminate\Support\ServiceProvider;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
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
         //
        // Exempt M-Pesa callback routes from CSRF protection
        VerifyCsrfToken::except([
            
            'mpesa/callback',
            
            
        ]);
        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }
    }
}
