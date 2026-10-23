<?php

declare(strict_types=1);

namespace Pipeandpiper\API\Serializers;

use League\Fractal\Pagination\CursorInterface;
use League\Fractal\Pagination\PaginatorInterface;
use League\Fractal\Resource\ResourceInterface;
use League\Fractal\Serializer\SerializerAbstract;

class PNPJSONAPIErrorSerializer extends SerializerAbstract
{
    /**
     * @param array<array-key, mixed> $data
     * @return array<string, mixed>
     */
    public function collection(?string $resourceKey, array $data): array
    {
        return [
            'data'    => [],
            'errors'  => $data,
            'status' => 'error',
            'message' => $data['message']
        ];
    }

    /**
     * @param array<array-key, mixed> $data
     * @return array<string, mixed>
     */
    public function item(?string $resourceKey, array $data): array
    {
        return [
            'data'    => [],
            'errors'  => $data,
            'status' => 'error',
            'message' => $data['message']
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function null(): ?array
    {
        return [
            'data'    => [],
            'errors'  => null,
            'status' => 'error',
            'message' => 'Unknown Error'
        ];
    }

    /**
     * @param array<array-key, mixed> $data
     * @return array<array-key, mixed>
     */
    public function includedData(ResourceInterface $resource, array $data): array
    {
        return [];
    }

    /**
     * @param array<array-key, mixed> $meta
     * @return array<array-key, mixed>
     */
    public function meta(array $meta): array
    {
        return [];
    }

    /** @return array<array-key, mixed> */
    public function paginator(PaginatorInterface $paginator): array
    {
        return [];
    }

    /** @return array<array-key, mixed> */
    public function cursor(CursorInterface $cursor): array
    {
        return [];
    }
}
