<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FilterableThing extends Model
{
    protected $fillable = [
        'name',
        'category',
        'score',
    ];
}