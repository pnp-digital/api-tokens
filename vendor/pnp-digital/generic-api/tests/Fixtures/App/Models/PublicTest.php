<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Pipeandpiper\API\Enums\AllowedMethodEnum;

class PublicTest extends Model
{
    public const array API_ALLOWED_METHODS = [
        AllowedMethodEnum::PUBLIC,
        AllowedMethodEnum::READ,
    ];

    protected $guarded = [];
}