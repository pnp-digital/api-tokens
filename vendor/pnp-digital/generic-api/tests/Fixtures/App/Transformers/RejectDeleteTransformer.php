<?php

namespace App\Transformers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Pipeandpiper\API\Interfaces\InputTransformerInterface;

class RejectDeleteTransformer implements InputTransformerInterface
{
    public function transform(array $input): array
    {
        return $input;
    }

    public function after($input, Model $model): void
    {
    }

    public function beforeDelete(array $ids, string $model): void
    {
        throw ValidationException::withMessages([
            'id' => ['Deletion rejected by transformer'],
        ]);
    }
}