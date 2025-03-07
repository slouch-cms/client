<?php

namespace SlouchCMS\Client;

use Phnxdgtl\UnnamedCmsClient\UnnamedCmsClientMiddleware;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

class SlouceCMSServiceProvider extends ServiceProvider
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
