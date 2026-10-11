<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('public-read', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));
        RateLimiter::for('public-booking', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('admin', fn (Request $request) => Limit::perMinute(120)->by((string) $request->user()?->id));
    }
}
