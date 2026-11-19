<?php

namespace App\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Create an entry of any type (note, prayer_need, goal, outcome, key_date) and link it to people.
 * The first person is the primary one. Used by the web UI and the API alike.
 */
class CreateEntry
{
    /**
     * @param  int[]  $personIds   first = primary
     * @param  array  $attributes  the entry's own columns (date, title, body, significance, ...)
     * @param  array  $source      provenance, e.g. ['source' => 'api', 'source_token_name' => 'Laptop']
     */
    public function execute(int $userId, string $type, array $personIds, array $attributes, array $source = []): Model
    {
        $class = config("entry_types.types.{$type}.model");

        return DB::transaction(function () use ($class, $userId, $personIds, $attributes, $source) {
            $entry = $class::create($attributes + $source + ['user_id' => $userId, 'logged_at' => now()]);

            foreach (array_values($personIds) as $i => $personId) {
                $entry->persons()->attach($personId, ['is_primary' => $i === 0]);
            }

            return $entry;
        });
    }
}
