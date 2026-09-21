<?php

namespace App\Providers;

use App\Notifications\Channels\PushChannel;
use App\Services\Push\FcmPushDriver;
use App\Services\Push\LogPushDriver;
use App\Services\Push\PushDriver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PushDriver::class, function ($app) {
            $credentials = config('services.fcm.credentials');

            if ($credentials && is_file($credentials) && class_exists(\Kreait\Firebase\Factory::class)) {
                return $app->make(FcmPushDriver::class);
            }

            return $app->make(LogPushDriver::class);
        });
    }

    public function boot(): void
    {
        RateLimiter::for('api-user', function (Request $request) {
            return Limit::perMinute(100)->by($request->user()?->id ?: $request->ip());
        });

        Notification::extend('push', fn ($app) => $app->make(PushChannel::class));
    }
}
