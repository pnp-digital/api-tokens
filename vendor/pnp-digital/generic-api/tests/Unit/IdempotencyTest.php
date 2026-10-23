<?php

namespace Tests\Unit;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Pipeandpiper\API\Http\Middleware\ApiIdempotency;
use Tests\TestCase;

class IdempotencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_request_without_idempotency_header_passes_through(): void
    {
        $request = Request::create('/api/v1/user', 'POST', ['name' => 'Alice']);
        $calls = 0;

        $response = (new ApiIdempotency())->handle($request, function() use (&$calls) {
            $calls++;
            return response()->json(['result' => 'created']);
        });

        $this->assertSame(1, $calls);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['result' => 'created'], json_decode($response->getContent(), true));
    }

    public function test_first_idempotent_request_is_processed(): void
    {
        $request = $this->request(10, ['name' => 'Alice']);
        $calls = 0;

        $response = (new ApiIdempotency())->handle($request, function() use (&$calls) {
            $calls++;
            return response()->json(['result' => 'created'], 201);
        });

        $this->assertSame(1, $calls);
        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame(['result' => 'created'], json_decode($response->getContent(), true));
    }

    public function test_identical_request_with_same_sequence_returns_original_response_without_executing_again(): void
    {
        $middleware = new ApiIdempotency();
        $calls = 0;

        $first = $middleware->handle($this->request(10, ['name' => 'Alice']), function() use (&$calls) {
            $calls++;
            return response()->json(['call' => $calls], 201);
        });

        $second = $middleware->handle($this->request(10, ['name' => 'Alice']), function() use (&$calls) {
            $calls++;
            return response()->json(['call' => $calls], 201);
        });

        $this->assertSame(1, $calls);
        $this->assertSame($first->getContent(), $second->getContent());
        $this->assertSame(201, $second->getStatusCode());
    }

    public function test_same_sequence_with_different_request_is_rejected(): void
    {
        $middleware = new ApiIdempotency();

        $middleware->handle($this->request(10, ['name' => 'Alice']), fn() => response()->json(['result' => 'created']));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('X-Idempotency sequence has already been used for a different request');

        $middleware->handle($this->request(10, ['name' => 'Bob']), fn() => response()->json(['result' => 'created']));
    }

    public function test_lower_sequence_is_rejected(): void
    {
        $middleware = new ApiIdempotency();

        $middleware->handle($this->request(10, ['name' => 'Alice']), fn() => response()->json(['result' => 'created']));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('X-Idempotency sequence has already passed');

        $middleware->handle($this->request(9, ['name' => 'Bob']), fn() => response()->json(['result' => 'created']));
    }

    public function test_higher_sequence_is_processed(): void
    {
        $middleware = new ApiIdempotency();
        $calls = 0;

        $middleware->handle($this->request(10, ['name' => 'Alice']), function() use (&$calls) {
            $calls++;
            return response()->json(['result' => 'first']);
        });

        $middleware->handle($this->request(15, ['name' => 'Bob']), function() use (&$calls) {
            $calls++;
            return response()->json(['result' => 'second']);
        });

        $this->assertSame(2, $calls);
    }

    public function test_invalid_sequence_is_rejected(): void
    {
        $request = Request::create('/api/v1/user', 'POST', ['name' => 'Alice']);
        $request->headers->set('X-Idempotency', 'nope');

        try {
            (new ApiIdempotency())->handle($request, fn() => response()->json([]));
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('idempotency', $e->errors());
        }
    }

    public function test_zero_sequence_is_rejected(): void
    {
        $request = $this->request(0, ['name' => 'Alice']);

        try {
            (new ApiIdempotency())->handle($request, fn() => response()->json([]));
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('idempotency', $e->errors());
        }
    }

    public function test_failed_request_does_not_consume_sequence(): void
    {
        $middleware = new ApiIdempotency();
        $calls = 0;

        try {
            $middleware->handle($this->request(10, ['name' => 'Alice']), function() use (&$calls) {
                $calls++;
                throw new \RuntimeException('Failed');
            });
        } catch (\RuntimeException) {
        }

        $response = $middleware->handle($this->request(10, ['name' => 'Alice']), function() use (&$calls) {
            $calls++;
            return response()->json(['result' => 'created']);
        });

        $this->assertSame(2, $calls);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_same_sequence_for_different_path_is_rejected(): void
    {
        $middleware = new ApiIdempotency();

        $middleware->handle($this->request(10, ['name' => 'Alice']), fn() => response()->json(['result' => 'created']));

        $request = Request::create(
            '/api/v1/another_resource',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['name' => 'Alice'], JSON_THROW_ON_ERROR)
        );

        $request->headers->set('X-Idempotency', '10');
        $request->setUserResolver(fn() => auth()->user());

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('X-Idempotency sequence has already been used for a different request');

        $middleware->handle($request, fn() => response()->json(['result' => 'created']));
    }

    public function test_idempotency_sequences_are_independent_between_bearer_tokens(): void
    {
        $middleware = new ApiIdempotency();

        $first = $this->request(10, ['name' => 'Alice']);
        $first->headers->set('Authorization', 'Bearer consumer-one');

        $second = $this->request(10, ['name' => 'Bob']);
        $second->headers->set('Authorization', 'Bearer consumer-two');

        $calls = 0;

        $middleware->handle($first, function() use (&$calls) {
            $calls++;
            return response()->json(['result' => 'first']);
        });

        $middleware->handle($second, function() use (&$calls) {
            $calls++;
            return response()->json(['result' => 'second']);
        });

        $this->assertSame(2, $calls);
    }

    private function request(int $sequence, array $body): Request
    {
        $request = Request::create(
            '/api/v1/user',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($body, JSON_THROW_ON_ERROR)
        );

        $request->headers->set('X-Idempotency', (string)$sequence);
        $request->setUserResolver(fn() => auth()->user());

        return $request;
    }
}