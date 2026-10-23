<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Pipeandpiper\API\Interfaces\ApiFilterInterface;
use App\Transformers\TestInputTransformer;

class TransformedTest extends Model implements ApiFilterInterface
{
    public const array API_TRANSFORMERS = [
        TestInputTransformer::class,
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