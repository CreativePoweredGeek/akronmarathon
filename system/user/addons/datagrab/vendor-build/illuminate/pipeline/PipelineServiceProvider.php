<?php

namespace BoldMinded\DataGrab\Dependency\Illuminate\Pipeline;

use BoldMinded\DataGrab\Dependency\Illuminate\Contracts\Pipeline\Hub as PipelineHubContract;
use BoldMinded\DataGrab\Dependency\Illuminate\Contracts\Support\DeferrableProvider;
use BoldMinded\DataGrab\Dependency\Illuminate\Support\ServiceProvider;
class PipelineServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(PipelineHubContract::class, Hub::class);
        $this->app->bind('pipeline', fn($app) => new Pipeline($app));
    }
    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [PipelineHubContract::class, 'pipeline'];
    }
}
