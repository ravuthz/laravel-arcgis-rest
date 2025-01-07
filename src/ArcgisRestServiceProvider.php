<?php

namespace Ravuthz\ArcgisRest;

use Illuminate\Support\ServiceProvider;

class ArcgisRestServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Register bindings or services.
        $this->app->register(ArcgisRestService::class);
    }

    public function boot()
    {
        // Publish configuration or assets, register routes, etc.
    }
}
