<?php

namespace Pipeandpiper\API\Http\Middleware;

use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class ApiIdempotency
{
    /**
     * Apply optional sequential idempotency to an API request.
     *
     * Requests without an X-Idempotency header are passed through unchanged.
     *
     * @param Request $request
     * @param Closure(Request): Response $next
     * @return Response
     * @throws LockTimeoutException
     */
    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->header('X-Idempotency');

        /*
         * Idempotency is entirely optional. If the header is not supplied,
         * preserve the existing API flow unchanged.
         */
        if ($header === null) {
            return $next($request);
        }

        $sequence = filter_var($header, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($sequence === false) {
            throw ValidationException::withMessages([
                'idempotency' => ['X-Idempotency must be a positive integer'],
            ]);
        }

        $consumer = $this->consumer($request);
        $key = 'generic-api:idempotency:' . $consumer;

        /** @var Response $response */
        $response = Cache::lock($key . ':lock', 30)->block(5, function() use ($request, $next, $key, $sequence): Response {
                /** @var array{sequence: int, request_hash: string, response: string, status: int}|null $state */
                $state = Cache::get($key);
                $requestHash = $this->requestHash($request);

                if ($state !== null && $sequence < $state['sequence']) {
                    throw ValidationException::withMessages([
                        'idempotency' => [
                            'X-Idempotency sequence has already passed',
                        ],
                    ]);
                }

                if ($state !== null && $sequence === $state['sequence']) {
                    if ($state['request_hash'] !== $requestHash) {
                        throw ValidationException::withMessages([
                            'idempotency' => [
                                'X-Idempotency sequence has already been used for a different request',
                            ],
                        ]);
                    }

                    return response($state['response'], $state['status'], ['Content-Type' => 'application/json']);
                }

                $response = $next($request);
                Cache::forever($key, [
                    'sequence' => $sequence,
                    'request_hash' => $requestHash,
                    'response' => $response->getContent(),
                    'status' => $response->getStatusCode(),
                ]);

                return $response;
            }
        );
        return $response;
    }

    /**
     * Identify the API consumer without storing its bearer token.
     */
    private function consumer(Request $request): string
    {
        $token = $request->bearerToken();

        if ($token !== null) {
            return 'token:' . hash('sha256', $token);
        }

        if ($request->hasSession()) {
            return 'session:' . hash('sha256', $request->session()->getId());
        }

        /** @var string|int|null $identifier */
        $identifier = $request->user()?->getAuthIdentifier();
        return 'user:' . (string)$identifier;
    }

    /**
     * Identify the request associated with an idempotency sequence.
     */
    private function requestHash(Request $request): string
    {
        return hash('sha256', implode("\n", [
            $request->method(),
            $request->path(),
            $request->getQueryString() ?? '',
            $request->getContent(),
        ]));
    }
}