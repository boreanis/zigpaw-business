<?php

namespace App\Providers;

use App\Auth\NullUserProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

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
        Auth::provider('null', fn (): NullUserProvider => new NullUserProvider);

        $caBundlePath = config('platform.ca_bundle_path');
        if (is_string($caBundlePath) && $caBundlePath !== '') {
            if (! is_file($caBundlePath) || ! is_readable($caBundlePath)) {
                throw new RuntimeException('The configured Platform CA bundle is not readable.');
            }

            Http::globalOptions(['verify' => $caBundlePath]);
        }
    }
}
