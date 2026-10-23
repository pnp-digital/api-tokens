<?php

namespace Pipeandpiper\ApiTokens;

use Illuminate\Support\ServiceProvider;
use Pipeandpiper\API\Interfaces\ApiTokenResolverInterface;
use Pipeandpiper\ApiTokens\Services\ApiTokenResolver;

class ApiTokensServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/api_tokens.php', 'api_tokens');

        $this->app->bind(ApiTokenResolverInterface::class, ApiTokenResolver::class);
    }
}
