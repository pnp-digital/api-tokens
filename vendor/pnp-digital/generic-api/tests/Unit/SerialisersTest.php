<?php

namespace Tests\Unit;

use League\Fractal\Pagination\CursorInterface;
use League\Fractal\Pagination\PaginatorInterface;
use League\Fractal\Resource\ResourceInterface;
use PHPUnit\Framework\TestCase;
use Pipeandpiper\API\Serializers\PNPJSONAPIErrorSerializer;
use Pipeandpiper\API\Serializers\PNPJSONAPISerializer;

class SerialisersTest extends TestCase
{
    public function test_collection_uses_default_pagination(): void
    {
        $serializer = new PNPJSONAPISerializer();

        $result = $serializer->collection(null, [
            ['id' => 1, 'name' => 'One'],
            ['id' => 2, 'name' => 'Two'],
        ]);

        $this->assertSame([
            'data' => [
                ['id' => 1, 'name' => 'One'],
                ['id' => 2, 'name' => 'Two'],
            ],
            'errors' => [],
            'status' => 'success',
            'message' => '',
            'pagination' => [
                'sort' => 'id',
                'page_size' => 50,
                'page' => 1,
                'total_pages' => 0.0,
            ],
        ], $result);
    }

    public function test_collection_uses_configured_pagination(): void
    {
        $serializer = new PNPJSONAPISerializer();

        $serializer->setPagination(3, 25, 'name', 61);

        $result = $serializer->collection(null, [
            ['id' => 51, 'name' => 'Test'],
        ]);

        $this->assertSame([
            'data' => [
                ['id' => 51, 'name' => 'Test'],
            ],
            'errors' => [],
            'status' => 'success',
            'message' => '',
            'pagination' => [
                'sort' => 'name',
                'page_size' => 25,
                'page' => 3,
                'total_pages' => 3.0,
            ],
        ], $result);
    }

    public function test_item_returns_single_item_response(): void
    {
        $serializer = new PNPJSONAPISerializer();

        $serializer->setPagination(1, 10, 'name', 12);

        $result = $serializer->item(null, ['id' => 1, 'name' => 'Test']);

        $this->assertSame(['id' => 1, 'name' => 'Test'], $result['data']);
        $this->assertSame([], $result['errors']);
        $this->assertSame('success', $result['status']);
        $this->assertSame('', $result['message']);
        $this->assertSame([
            'sort' => 'name',
            'page_size' => 10,
            'page' => 1,
            'total_pages' => 2.0,
        ], $result['pagination']);
    }

    public function test_null_returns_success_with_null_data(): void
    {
        $serializer = new PNPJSONAPISerializer();

        $result = $serializer->null();

        $this->assertNull($result['data']);
        $this->assertSame([], $result['errors']);
        $this->assertSame('success', $result['status']);
        $this->assertSame('', $result['message']);
        $this->assertSame([
            'sort' => 'id',
            'page_size' => 50,
            'page' => 1,
            'total_pages' => 0.0,
        ], $result['pagination']);
    }

    public function test_collection_returns_error_response(): void
    {
        $serializer = new PNPJSONAPIErrorSerializer();

        $error = [
            'message' => 'Something went wrong',
            'code' => 123,
        ];

        $this->assertSame([
            'data' => [],
            'errors' => $error,
            'status' => 'error',
            'message' => 'Something went wrong',
        ], $serializer->collection(null, $error));
    }

    public function test_item_returns_error_response(): void
    {
        $serializer = new PNPJSONAPIErrorSerializer();

        $error = [
            'message' => 'Not found',
            'code' => 404,
        ];

        $this->assertSame([
            'data' => [],
            'errors' => $error,
            'status' => 'error',
            'message' => 'Not found',
        ], $serializer->item(null, $error));
    }

    public function test_null_returns_unknown_error(): void
    {
        $serializer = new PNPJSONAPIErrorSerializer();

        $this->assertSame([
            'data' => [],
            'errors' => null,
            'status' => 'error',
            'message' => 'Unknown Error',
        ], $serializer->null());
    }

    public function test_included_data_is_empty(): void
    {
        $serializer = new PNPJSONAPIErrorSerializer();
        $resource = $this->createStub(ResourceInterface::class);

        $this->assertSame([], $serializer->includedData($resource, ['anything']));
    }

    public function test_meta_is_empty(): void
    {
        $serializer = new PNPJSONAPIErrorSerializer();

        $this->assertSame([], $serializer->meta(['anything' => 'value']));
    }

    public function test_paginator_is_empty(): void
    {
        $serializer = new PNPJSONAPIErrorSerializer();
        $paginator = $this->createStub(PaginatorInterface::class);

        $this->assertSame([], $serializer->paginator($paginator));
    }

    public function test_cursor_is_empty(): void
    {
        $serializer = new PNPJSONAPIErrorSerializer();
        $cursor = $this->createStub(CursorInterface::class);

        $this->assertSame([], $serializer->cursor($cursor));
    }
}