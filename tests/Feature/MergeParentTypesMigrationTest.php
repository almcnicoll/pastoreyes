<?php

namespace Tests\Feature;

use Tests\Feature\Api\ApiTestCase;
use Illuminate\Support\Facades\DB;

class MergeParentTypesMigrationTest extends ApiTestCase
{
    private function type(string $name, ?string $inverse, ?int $userId = null): int
    {
        return DB::table('relationship_types')->insertGetId([
            'user_id' => $userId, 'name' => $name, 'inverse_name' => $inverse, 'is_directional' => (bool) $inverse,
            'is_preset' => $userId === null, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function link(int $from, int $to, int $type): int
    {
        return DB::table('relationships')->insertGetId([
            'user_id' => $this->user->id, 'person_id' => $from, 'related_person_id' => $to,
            'relationship_type_id' => $type, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_relationships_move_to_the_first_parent_type_and_the_extra_is_removed(): void
    {
        $first = $this->type('parent', 'child');
        $second = $this->type('parent', 'child');
        $mentor = $this->type('mentor', 'mentee');
        $mine = $this->type('parent', 'child', $this->user->id);   // another owner's own type is left alone

        [$a, $b, $c, $d] = [$this->person('A', 'One')->id, $this->person('B', 'Two')->id, $this->person('C', 'Three')->id, $this->person('D', 'Four')->id];

        $onSecond = $this->link($a, $b, $second);
        $duplicate = $this->link($c, $d, $second);   // the same pair is already on the first type
        $this->link($c, $d, $first);
        $other = $this->link($a, $c, $mentor);
        $onMine = $this->link($b, $d, $mine);

        (require base_path('database/migrations/2024_01_01_000026_merge_duplicate_parent_relationship_type.php'))->up();

        $this->assertSame($first, (int) DB::table('relationships')->where('id', $onSecond)->value('relationship_type_id'));
        $this->assertNull(DB::table('relationships')->where('id', $duplicate)->first());
        $this->assertSame(1, DB::table('relationships')->where('person_id', $c)->where('related_person_id', $d)->count());
        $this->assertSame($mentor, (int) DB::table('relationships')->where('id', $other)->value('relationship_type_id'));
        $this->assertSame($mine, (int) DB::table('relationships')->where('id', $onMine)->value('relationship_type_id'));

        $this->assertNull(DB::table('relationship_types')->find($second));
        $this->assertNotNull(DB::table('relationship_types')->find($first));
        $this->assertNotNull(DB::table('relationship_types')->find($mine));
    }

    public function test_running_it_when_there_is_only_one_parent_type_changes_nothing(): void
    {
        $only = $this->type('parent', 'child');
        $link = $this->link($this->person('A', 'One')->id, $this->person('B', 'Two')->id, $only);

        (require base_path('database/migrations/2024_01_01_000026_merge_duplicate_parent_relationship_type.php'))->up();

        $this->assertSame($only, (int) DB::table('relationships')->where('id', $link)->value('relationship_type_id'));
        $this->assertNotNull(DB::table('relationship_types')->find($only));
    }
}
