<?php

namespace App\Models;

use App\Transformers\RejectDeleteTransformer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Pipeandpiper\API\Interfaces\ApiFilterInterface;

class RejectDeleteTest extends Model implements ApiFilterInterface
{
    public const array API_TRANSFORMERS = [
        RejectDeleteTransformer::class,
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