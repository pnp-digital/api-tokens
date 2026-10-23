<?php

namespace Pipeandpiper\ApiTokens;

use Illuminate\Support\ServiceProvider;
use Pipeandpiper\API\Interfaces\ApiTokenResolverInterface;
use Pipeandpiper\ApiTokens\Console\Commands\GenerateJwtKeys;
use Pipeandpiper\ApiTokens\Services\ApiTokenResolver;

class ApiTokenServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/api_tokens.php', 'api_tokens');

        $this->app->bind(ApiTokenResolverInterface::class, ApiTokenResolver::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([GenerateJwtKeys::class]);
        }
    }
}
