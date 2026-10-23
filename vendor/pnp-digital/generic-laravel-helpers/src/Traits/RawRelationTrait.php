<?php /** @noinspection PhpMultipleClassDeclarationsInspection */

namespace Pipeandpiper\Traits;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

trait RawRelationTrait
{
    /**
     * Get the foreign key relation attached to a column in the database
     * @param string $tableName Table name of current column
     * @param string $columnName Current column name
     * @return array Array representing the relation, or empty array of no relation on this column
     */
    public function getRawRelation(string $tableName, string $columnName): array
    {
        $expire = Carbon::now()->addMinutes(5);
        $refs = Cache::remember(
            'pnp-digital/generic-laravel-helpers:raw_relations/' . DB::connection()->getDatabaseName(), $expire, function() {
            return DB::query()->selectRaw(
                "REFERENCED_TABLE_NAME AS `table`, REFERENCED_COLUMN_NAME AS `column`, TABLE_NAME, COLUMN_NAME
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                WHERE REFERENCED_TABLE_SCHEMA = DATABASE()",
            )->get();
        });
        $f = $refs->where("TABLE_NAME", "=", $tableName)
            ->where("COLUMN_NAME", "=", $columnName)
            ->first();
        if ($f) {
            return (array)$f;
        }
        return [];
    }
}