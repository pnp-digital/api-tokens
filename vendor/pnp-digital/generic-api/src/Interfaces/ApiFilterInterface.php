<?php

namespace Pipeandpiper\API\Interfaces;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Pipeandpiper\API\Enums\AllowedMethodEnum;

interface ApiFilterInterface
{
    /**
     * The API methods allowed for this endpoint
     */
    public const API_ALLOWED_METHODS = [
        AllowedMethodEnum::CREATE,
        AllowedMethodEnum::READ,
        AllowedMethodEnum::UPDATE,
        AllowedMethodEnum::DELETE,
    ];

    /**
     * The transformer to use to translate data for UPDATE
     * or CREATE calls, instantiated and called immediately
     * before saving data to the database.
     */
    public const API_TRANSFORMERS = [];

    /**
     * Before executing an eloquent query to obtain API results, allow the query to be modified
     *
     * @param Builder<Model> $b input query
     * @return Builder<Model> output query
     */
    public function apiFilter(Builder $b): Builder;

    /**
     * After API query is completed, before sending the data, allow for adjustment of the output
     * @param array<int, array<string, mixed>> $a input
     * @return array<int, array<string, mixed>> output
     */
    public function apiPostProcess(array $a): array;
}
