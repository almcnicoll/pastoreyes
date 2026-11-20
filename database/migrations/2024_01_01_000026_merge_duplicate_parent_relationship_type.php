<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Some databases have two "parent / child" relationship types, which makes "parent" ambiguous. Keep the first
 * (lowest id) of each owner's, move every relationship that uses a later one onto it, then remove the later ones.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $groups = DB::table('relationship_types')
                ->where('name', 'parent')->where('inverse_name', 'child')
                ->orderBy('id')->get()->groupBy('user_id');   // null user_id = the global presets

            foreach ($groups as $types) {
                $keep = $types->first()->id;

                foreach ($types->skip(1) as $extra) {
                    // A pair already recorded with the kept type would break the unique index: that row is the duplicate
                    // (ids are fetched first: MySQL will not delete from a table it is also selecting from)
                    $duplicates = DB::table('relationships as r')
                        ->where('r.relationship_type_id', $extra->id)
                        ->whereExists(fn($q) => $q->selectRaw(1)->from('relationships as k')
                            ->whereColumn('k.person_id', 'r.person_id')
                            ->whereColumn('k.related_person_id', 'r.related_person_id')
                            ->where('k.relationship_type_id', $keep))
                        ->pluck('r.id');
                    DB::table('relationships')->whereIn('id', $duplicates)->delete();

                    DB::table('relationships')->where('relationship_type_id', $extra->id)->update(['relationship_type_id' => $keep]);
                    DB::table('relationship_types')->where('id', $extra->id)->delete();
                }
            }
        });
    }

    // The merge cannot be undone: which relationships used the removed type is not recorded.
    public function down(): void
    {
    }
};
