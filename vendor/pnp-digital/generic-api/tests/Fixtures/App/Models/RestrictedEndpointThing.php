<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Pipeandpiper\API\Enums\AllowedMethodEnum;
use Pipeandpiper\API\Interfaces\ApiFilterInterface;

class RestrictedEndpointThing extends Model implements ApiFilterInterface
{
    public const API_ALLOWED_METHODS = [
        AllowedMethodEnum::READ,
        AllowedMethodEnum::UPDATE,
    ];

    protected $guarded = [];

    public function apiFilter(Builder $b): Builder
    {
        return $b;
    }

    public function apiPostProcess(array $a): array
    {
        return $a;
    }
}