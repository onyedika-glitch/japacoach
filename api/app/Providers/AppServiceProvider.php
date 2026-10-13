<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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
        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));

        // Requests that cost money (AI scoring, payments) are limited per user.
        RateLimiter::for('ai', fn (Request $request) => Limit::perMinute(10)->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('uploads', fn (Request $request) => Limit::perMinute(40)->by($request->user()?->id ?: $request->ip()));
    }
}
