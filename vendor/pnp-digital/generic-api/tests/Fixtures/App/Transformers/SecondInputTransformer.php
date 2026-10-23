<?php

namespace App\Transformers;

use Illuminate\Database\Eloquent\Model;
use Pipeandpiper\API\Interfaces\InputTransformerInterface;

class SecondInputTransformer implements InputTransformerInterface
{
    public function transform(array $input): array
    {
        $input['name'] .= '-second';

        return $input;
    }

    public function after($input, Model $model): void
    {
    }

    public function beforeDelete(array $ids, string $model): void
    {
    }
}