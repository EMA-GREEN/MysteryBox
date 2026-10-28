<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // GetPageSpeed / minimal php-curl builds may omit SSL version constants Guzzle 7 expects.
        if (! defined('CURL_SSLVERSION_TLSv1_2')) {
            define('CURL_SSLVERSION_TLSv1_2', 6);
        }
        if (! defined('CURL_SSLVERSION_TLSv1_3')) {
            define('CURL_SSLVERSION_TLSv1_3', 7);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
