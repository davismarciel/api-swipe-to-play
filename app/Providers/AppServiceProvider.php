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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Auto-detect Redis host for Docker environments
        // If REDIS_HOST is not set, default to 'redis' (Docker service name)
        // Users can override with REDIS_HOST=127.0.0.1 for local development
        if (!env('REDIS_HOST')) {
            config(['database.redis.default.host' => 'redis']);
            config(['database.redis.cache.host' => 'redis']);
        }

        \Dedoc\Scramble\Scramble::configure()
            ->withDocumentTransformers(function (\Dedoc\Scramble\Support\Generator\OpenApi $openApi) {
                $openApi->secure(
                    \Dedoc\Scramble\Support\Generator\SecurityScheme::http('bearer', 'JWT')
                );
            });
    }
}
