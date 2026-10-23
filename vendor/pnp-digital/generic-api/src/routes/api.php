<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Pipeandpiper\API\Http\Controllers\ApiController;
use Pipeandpiper\API\Serializers\PNPJSONAPISerializer;
use Pipeandpiper\API\Http\Middleware\ApiIdempotency;

if (!defined('GENERIC_API_VERSION')) {
    define('GENERIC_API_VERSION', 1);
}

Route::group(['prefix'=>'/api/v' . GENERIC_API_VERSION], function() {

    /* Authentication endpoints */
    foreach (["login", "logout", "register", "resetpassword", "public"] as $authEndpoint) {
        if ($authEndpoint === "public") {
            foreach(["get", "post", "put", "delete"] as $method) {
                Route::$method('/' . $authEndpoint . '/{route}', function (Request $request, string $route) use ($authEndpoint) {
                    return (new ApiController())->__invoke($request, new PNPJSONAPISerializer(), $authEndpoint . '/' . $route);
                })->where('route', '(.*)');
            }
        } else {
            Route::post('/' . $authEndpoint, function(Request $request) use ($authEndpoint) {
                return (new ApiController())->__invoke($request, new PNPJSONAPISerializer(), $authEndpoint);
            });
        }
    }

    /* Transaction endpoints */
    foreach (["start", "commit", "rollback"] as $transactionEndpoint) {
        Route::post('/transaction/' . $transactionEndpoint, function(Request $request) use ($transactionEndpoint) {
            return (new ApiController())->__invoke(
                $request,
                new PNPJSONAPISerializer(),
                'transaction' . ucfirst($transactionEndpoint)
            );
        })->middleware(['api', 'auth:sanctum,generic-api']);
    }

    /* Everything else */
    foreach(["get", "post", "put", "delete"] as $method) {
        $route = Route::$method('/{route}', ApiController::class)->where('route', '(.*)')->middleware(['api', 'auth:sanctum,generic-api']);
        if ($method !== 'get') {
            $route->middleware(ApiIdempotency::class);
        }
    }
});

Route::group(['prefix'=>'/api/frontend'], function() {
    foreach(["get", "post", "put", "delete"] as $method) {
        $route = Route::$method('/{route}', ApiController::class)->where('route', '(.*)')->middleware(['web', 'auth:sanctum,generic-api']);
        if ($method !== 'get') {
            $route->middleware(ApiIdempotency::class);
        }
    }
});
