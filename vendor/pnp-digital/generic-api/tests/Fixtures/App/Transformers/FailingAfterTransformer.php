<?php

namespace App\Transformers;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Pipeandpiper\API\Interfaces\InputTransformerInterface;

class FailingAfterTransformer implements InputTransformerInterface
{
    public function transform(array $input): array
    {
        $input['name'] = 'transformed-' . $input['name'];

        return $input;
    }

    public function after($input, Model $model): void
    {
        throw new Exception('Transformer after failure');
    }

    public function beforeDelete(array $ids, string $model): void
    {
    }
}