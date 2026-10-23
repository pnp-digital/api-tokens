<?php

namespace App\Models;

use App\Transformers\InvalidInputTransformer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Pipeandpiper\API\Interfaces\ApiFilterInterface;

class InvalidTransformedTest extends Model implements ApiFilterInterface
{
    public const array API_TRANSFORMERS = [
        InvalidInputTransformer::class,
    ];

    protected $table = 'transformed_tests';

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