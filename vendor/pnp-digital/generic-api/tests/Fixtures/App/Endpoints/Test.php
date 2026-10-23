<?php

namespace App\Endpoints;

use Illuminate\Http\Request;
use League\Fractal\Resource\Item;
use Pipeandpiper\API\Interfaces\ApiEndpointDeleteInterface;
use Pipeandpiper\API\Interfaces\ApiEndpointGetInterface;
use Pipeandpiper\API\Interfaces\ApiEndpointPostInterface;
use Pipeandpiper\API\Interfaces\ApiEndpointPutInterface;
use Pipeandpiper\API\Serializers\PNPJSONAPISerializer;
use Pipeandpiper\API\Transformers\NullTransformer;

class Test implements ApiEndpointGetInterface, ApiEndpointPostInterface, ApiEndpointPutInterface, ApiEndpointDeleteInterface
{
    public function get(Request $request, PNPJSONAPISerializer $serializer, string $detailMode, int $page, int $pageSize, string $orderBy): Item
    {
        return $this->response('GET', $request);
    }

    public function post(Request $request, PNPJSONAPISerializer $serializer, string $detailMode, int $page, int $pageSize, string $orderBy): Item
    {
        return $this->response('POST', $request);
    }

    public function put(Request $request, PNPJSONAPISerializer $serializer, string $detailMode, int $page, int $pageSize, string $orderBy): Item
    {
        return $this->response('PUT', $request);
    }

    public function delete(Request $request, PNPJSONAPISerializer $serializer, string $detailMode, int $page, int $pageSize, string $orderBy): Item
    {
        return $this->response('DELETE', $request);
    }

    private function response(string $method, Request $request): Item
    {
        return new Item([
            [
                'method' => $method,
                'value' => $request->input('value'),
            ],
        ], new NullTransformer());
    }
}