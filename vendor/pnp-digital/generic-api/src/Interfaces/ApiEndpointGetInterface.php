<?php

namespace Pipeandpiper\API\Interfaces;

use Illuminate\Http\Request;
use League\Fractal\Resource\Item;
use Pipeandpiper\API\Serializers\PNPJSONAPISerializer;

/**
 * Implement this interface on a class which can handle Generic API 'GET' requests
 */
interface ApiEndpointGetInterface
{    /**
 * Handle Generic API GET request
 * @param Request $request Request
 * @param PNPJSONAPISerializer $serializer Serializer. Use for pagination
 * @param string $detailMode Detail mode of API results. Can be 'basic' or 'extended'
 * @param int $page Page number to retrieve (default 1)
 * @param int $pageSize Size of pages (default 50)
 * @param string $orderBy Order by field (default 'id')
 * @return Item Fractal item
 */
    public function get(Request $request, PNPJSONAPISerializer $serializer, string $detailMode, int $page, int $pageSize, string $orderBy): Item;
}
