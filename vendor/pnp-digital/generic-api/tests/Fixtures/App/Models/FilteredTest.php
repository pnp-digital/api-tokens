<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Pipeandpiper\API\Interfaces\ApiFilterInterface;

class FilteredTest extends Model implements ApiFilterInterface
{
    protected $guarded = [];

    public function apiFilter(Builder $b): Builder
    {
        return $b->where('visible', true);
    }

    public function apiPostProcess(array $a): array
    {
        foreach ($a as &$row) {
            $row['name'] = strtoupper($row['name']);
        }

        return $a;
    }
}