<?php

namespace Pipeandpiper\API;

use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use Pipeandpiper\API\Commands\ListApiEndpointsCommand;
use Pipeandpiper\API\Commands\Typescript\GenerateModelTypescriptTypings;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Pipeandpiper\API\Interfaces\ApiTokenResolverInterface;

class GenericApiProvider extends ServiceProvider
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

        config()->set('auth.guards.generic-api', ['driver' => 'generic-api-token']);

        Auth::viaRequest('generic-api-token', function(Request $request): ?Authenticatable {
            if (!$this->app->bound(ApiTokenResolverInterface::class)) {
                return null;
            }

            $token = $request->bearerToken();
            if ($token === null) {
                return null;
            }

            /** @var ApiTokenResolverInterface $resolver */
            $resolver = $this->app->make(ApiTokenResolverInterface::class);
            $userId = $resolver->resolveToken($token);

            if ($userId === null) {
                return null;
            }

            return \App\Models\User::find($userId);
        });

        $this->loadRoutesFrom(__DIR__ . '/routes/api.php');

        $this->mergeConfigFrom(
            __DIR__.'/config/typescript-transformer.php', 'typescript-transformer',
        );

        if ($this->app->runningInConsole()) {
            $this->commands([
                ListApiEndpointsCommand::class,
                GenerateModelTypescriptTypings::class,
            ]);
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
