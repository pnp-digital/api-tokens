<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Pipeandpiper\API\Enums\AllowedMethodEnum;

class ReadOnlyTest extends Model
{
    public const array API_ALLOWED_METHODS = [
        AllowedMethodEnum::READ,
    ];

    protected $guarded = [];
}