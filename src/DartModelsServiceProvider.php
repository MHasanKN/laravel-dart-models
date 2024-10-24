<?php

namespace Mhasankn\DartModels;

use Illuminate\Support\ServiceProvider;

class DartModelsServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register()
    {
        // Register the command
        $this->commands([
            Console\Commands\GenerateDartModels::class,
        ]);
    }

    /**
     * Bootstrap services.
     */
    public function boot()
    {
        // Boot methods if any
    }
}
