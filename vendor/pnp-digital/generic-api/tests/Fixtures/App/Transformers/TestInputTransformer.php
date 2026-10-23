<?php

namespace App\Transformers;

use Illuminate\Database\Eloquent\Model;
use Pipeandpiper\API\Interfaces\InputTransformerInterface;

class TestInputTransformer implements InputTransformerInterface
{
    public function transform(array $input): array
    {
        $input['name'] = 'transformed-' . $input['name'];

        return $input;
    }

    public function after($input, Model $model): void
    {
        $model->update([
            'after_value' => 'after-' . $input['name'],
        ]);
    }

    public function beforeDelete(array $ids, string $model): void
    {
    }
}