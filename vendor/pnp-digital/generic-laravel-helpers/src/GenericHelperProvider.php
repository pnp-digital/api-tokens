<?php

namespace Pipeandpiper;

use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;

class GenericHelperProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot(): void
    {
        if (File::exists(__DIR__.'/../vendor/autoload.php')) {
            include __DIR__.'/../vendor/autoload.php';
        }
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register(): void
    {
    }
}
