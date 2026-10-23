<?php

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

if (!function_exists("getFieldMaxLength")) {

    function getFieldMaxLength(string $tableName, string $columnName): int|null
    {
        $expire = Carbon::now()->addMinutes(5);
        $refs = Cache::remember(
            'pnp-digital/generic-laravel-helpers:max_lengths/' . DB::connection()->getDatabaseName(), $expire, function() {
            return DB::query()->selectRaw(
                "TABLE_NAME, COLUMN_NAME, CHARACTER_MAXIMUM_LENGTH AS `length`, NUMERIC_PRECISION as `num_length`
                FROM INFORMATION_SCHEMA.columns
                WHERE TABLE_SCHEMA = DATABASE()",
            )->get();
        });
        $f = $refs->where("TABLE_NAME", "=", $tableName)
            ->where("COLUMN_NAME", "=", $columnName)
            ->first();
        if ($f) {
            return $f->length ?? $f->num_length ?? null;
        }
        return null;
    }

}