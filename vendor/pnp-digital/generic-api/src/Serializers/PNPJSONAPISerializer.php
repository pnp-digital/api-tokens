<?php

declare(strict_types=1);

namespace Pipeandpiper\API\Serializers;

use League\Fractal\Serializer\DataArraySerializer;

class PNPJSONAPISerializer extends DataArraySerializer
{
    private int $page = 1;
    private int $pageSize = 50;
    private string $orderBy = 'id';
    private int $recordCount = 0;

    public function setPagination(int $page, int $pageSize, string $orderBy, int $count): void
    {
        $this->page = $page;
        $this->pageSize = $pageSize;
        $this->orderBy = $orderBy;
        $this->recordCount = $count;
    }

    /**
     * @param array<array-key, mixed> $data
     * @return array<string, mixed>
     */
    public function collection(?string $resourceKey, array $data): array
    {
        return [
            'data'    => $data,
            'errors'  => [],
            'status' => 'success',
            'message' => '',
            'pagination' => [
                'sort' => $this->orderBy,
                'page_size' => $this->pageSize,
                'page' => $this->page,
                'total_pages' => ceil($this->recordCount / $this->pageSize),
            ]
        ];
    }

    /**
     * @param array<array-key, mixed> $data
     * @return array<string, mixed>
     */
    public function item(?string $resourceKey, array $data): array
    {
        return [
            'data'    => $data,
            'errors'  => [],
            'status' => 'success',
            'message' => '',
            'pagination' => [
                'sort' => $this->orderBy,
                'page_size' => $this->pageSize,
                'page' => $this->page,
                'total_pages' => ceil($this->recordCount / $this->pageSize),
            ]
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function null(): ?array
    {
        return [
            'data'    => null,
            'errors'  => [],
            'status' => 'success',
            'message' => '',
            'pagination' => [
                'sort' => $this->orderBy,
                'page_size' => $this->pageSize,
                'page' => $this->page,
                'total_pages' => ceil($this->recordCount / $this->pageSize),
            ],
        ];
    }
}
