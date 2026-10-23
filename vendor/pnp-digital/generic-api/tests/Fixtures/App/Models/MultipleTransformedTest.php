<?php

namespace App\Models;

use App\Transformers\SecondInputTransformer;
use App\Transformers\TestInputTransformer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Pipeandpiper\API\Interfaces\ApiFilterInterface;

class MultipleTransformedTest extends Model implements ApiFilterInterface
{
    public const array API_TRANSFORMERS = [
        TestInputTransformer::class,
        SecondInputTransformer::class,
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