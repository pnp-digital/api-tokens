<?php

namespace Pipeandpiper\API\Interfaces;

use Exception;
use Illuminate\Database\Eloquent\Model;

interface InputTransformerInterface
{
    /**
     * Called to transform API input into a new format before committing to the database
     * @param array<string, mixed> $input Input to transform
     * @return array<string, mixed> Output
     * @throws Exception Throwing an exception will prevent data being changed or added
     */
    public function transform(array $input): array;

    /**
     * Called to view API input after committing to the database
     * @param array<string, mixed> $input
     * @param Model $model
     * @return void
     */
    public function after($input, Model $model): void;

    /**
     * Called before deleting one or more records.
     *
     * @param array<int, int> $ids IDs of records to delete
     * @param class-string<Model> $model Model class being deleted
     * @throws Exception Throwing prevents deletion
     */
    public function beforeDelete(array $ids, string $model): void;
}
