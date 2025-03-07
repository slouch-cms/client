<?php

namespace SlouchCMS\Client;

// use SlouchCMS\Client\SlouchCMSMiddleware;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

class SlouchCMSServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        include __DIR__.'/routes.php';

        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('slouch-cms', SlouchCMSMiddleware::class);
    }
}
