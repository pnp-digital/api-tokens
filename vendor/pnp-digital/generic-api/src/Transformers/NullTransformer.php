<?php

namespace Pipeandpiper\API\Transformers;

use League\Fractal\TransformerAbstract;

/**
 * Does absolutely nothing to an array
 */
class NullTransformer extends TransformerAbstract
{
    /**
     * Transform array from itself to itself (does nothing)
     *
     * @template TKey of array-key
     * @template TValue
     * @param array<TKey, TValue> $array Input array
     * @return array<TKey, TValue> Output array
     */
    public function transform(array $array): array
    {
        return $array;
    }
}
