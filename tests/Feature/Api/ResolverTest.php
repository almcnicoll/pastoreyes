<?php

namespace Tests\Feature\Api;

use App\Models\Address;
use App\Models\KeyDate;
use App\Models\PersonName;
use App\Models\Relationship;
use App\Models\RelationshipType;

class ResolverTest extends ApiTestCase
{
    private function twoAls(): array
    {
        return [$this->person('Al', 'McNicoll', '1984-07-13'), $this->person('Al', 'McNicoll', '1991-02-02')];
    }

    public function test_two_people_of_one_name_are_ambiguous_and_a_write_saves_nothing(): void
    {
        $this->twoAls();

        $r = $this->api('POST', '/entries', ['type' => 'note', 'people' => ['Al McNicoll'], 'body' => 'Coffee']);

        $this->assertEnvelope($r, 'ambiguous')->assertStatus(409);
        $this->assertDatabaseCount('notes', 0);
        $r->assertJsonPath('data.problems.0.reference', 'Al McNicoll');
        $this->assertStringContainsString('13 Jul 1984', $r->json('message'));
        $this->assertStringContainsString("I haven't saved anything", $r->json('message'));
        $this->assertCount(2, $r->json('data.problems.0.candidates'));
        $this->assertContains('born', $r->json('data.problems.0.distinguishing_fields'));
    }

    public function test_on_a_read_ambiguity_is_a_200_with_candidates_and_how_to_specify(): void
    {
        $this->twoAls();

        $r = $this->api('POST', '/people/resolve', ['name' => 'Al McNicoll']);

        $this->assertEnvelope($r, 'ambiguous')->assertOk();
        $r->assertJsonPath('data.candidates.0.born', '1984-07-13');
        $r->assertJsonPath('data.how_to_specify.0.person.born', '1984-07-13');
        $this->assertStringContainsString('2 Feb 1991', $r->json('message'));
    }

    public function test_hints_narrow_the_candidates(): void
    {
        [$a, $b] = $this->twoAls();

        foreach ([['born' => '1984-07-13'], ['born' => '1984'], ['born' => '07-13']] as $hint) {
            $r = $this->api('POST', '/people/resolve', ['name' => 'Al McNicoll'] + $hint);
            $r->assertJsonPath('status', 'ok')->assertJsonPath('data.person.id', $a->id);
        }
        $this->api('POST', '/people/resolve', ['name' => 'Al McNicoll', 'born' => '1991'])->assertJsonPath('data.person.id', $b->id);
        $this->api('POST', '/people/resolve', ['name' => 'Al McNicoll', 'id' => $b->id])->assertJsonPath('data.person.id', $b->id);
        $this->api('POST', '/people/resolve', ['person' => $b->id])->assertJsonPath('data.person.id', $b->id);

        $r = $this->api('POST', '/entries', ['type' => 'note', 'people' => [['name' => 'Al McNicoll', 'born' => '1984']], 'body' => 'Coffee']);
        $r->assertCreated();
        $this->assertStringContainsString('Al McNicoll (13 Jul 1984)', $r->json('message'));
    }

    public function test_a_year_unknown_birthday_matches_on_day_and_month(): void
    {
        $p  = $this->person('Pat', 'Quill');
        $kd = KeyDate::create(['user_id' => $this->user->id, 'date' => '1900-03-04', 'year_unknown' => true, 'type' => 'birthday', 'is_recurring' => true, 'significance' => 3, 'logged_at' => now()]);
        $kd->persons()->attach($p->id, ['is_primary' => true]);
        $this->person('Pat', 'Quill', '1970-01-01');

        $this->api('POST', '/people/resolve', ['name' => 'Pat Quill', 'born' => '03-04'])->assertJsonPath('data.person.id', $p->id);
        $this->api('POST', '/people/resolve', ['name' => 'Pat Quill', 'born' => '1900-03-04'])->assertJsonPath('data.person.id', $p->id);
        $this->api('POST', '/people/resolve', ['name' => 'Pat Quill', 'born' => '1900'])->assertJsonPath('status', 'not_found');
    }

    public function test_every_name_a_person_has_is_matched_and_accents_and_case_are_ignored(): void
    {
        $p = $this->person('Caroline', 'Smith', null, null, ['middle_names' => 'Anne']);
        PersonName::create(['person_id' => $p->id, 'first_name' => 'Caroline', 'last_name' => 'McNicoll', 'type' => 'married', 'is_primary' => false]);
        $z = $this->person('Zoë', 'Brontë');

        $this->api('POST', '/people/resolve', ['name' => 'caroline mcnicoll'])->assertJsonPath('data.person.id', $p->id);   // married name
        $this->api('POST', '/people/resolve', ['name' => 'Anne Smith'])->assertJsonPath('data.person.id', $p->id);          // middle name, any order
        $this->api('POST', '/people/resolve', ['name' => 'smith caroline'])->assertJsonPath('data.person.id', $p->id);
        $this->api('POST', '/people/resolve', ['name' => 'zoe bronte'])->assertJsonPath('data.person.id', $z->id);          // accents ignored
    }

    public function test_an_exact_match_beats_a_partial_one_and_the_partial_is_mentioned(): void
    {
        $exact = $this->person('Pat', 'Lane');
        $this->person('Patrick', 'Lane');

        $r = $this->api('POST', '/people/resolve', ['name' => 'Pat Lane']);

        $r->assertJsonPath('status', 'ok')->assertJsonPath('data.person.id', $exact->id);
        $this->assertStringContainsString('Patrick Lane', $r->json('warnings.0'));

        // Typed less fully, both only partly match: that is ambiguous, not a guess
        $this->api('POST', '/people/resolve', ['name' => 'Pat'])->assertJsonPath('status', 'ambiguous');
    }

    public function test_city_gender_and_related_to_hints(): void
    {
        [$a, $b] = $this->twoAls();
        Address::create(['person_id' => $a->id, 'city' => 'Reading', 'postcode' => 'RG1 1AA', 'is_current' => true]);
        Address::create(['person_id' => $b->id, 'city' => 'Leeds', 'postcode' => 'LS1 1AA', 'is_current' => true]);
        $b->update(['gender' => 'female']);
        $caroline = $this->person('Caroline', 'McNicoll');
        $spouse   = RelationshipType::create(['name' => 'spouse', 'is_directional' => false, 'is_preset' => true]);
        Relationship::create(['user_id' => $this->user->id, 'person_id' => $a->id, 'related_person_id' => $caroline->id, 'relationship_type_id' => $spouse->id]);

        $this->api('POST', '/people/resolve', ['name' => 'Al McNicoll', 'city' => 'reading'])->assertJsonPath('data.person.id', $a->id);
        $this->api('POST', '/people/resolve', ['name' => 'Al McNicoll', 'city' => 'ls1'])->assertJsonPath('data.person.id', $b->id);   // postcode prefix
        $this->api('POST', '/people/resolve', ['name' => 'Al McNicoll', 'gender' => 'female'])->assertJsonPath('data.person.id', $b->id);
        $this->api('POST', '/people/resolve', ['name' => 'Al McNicoll', 'related_to' => ['person' => 'Caroline McNicoll', 'type' => 'spouse']])
            ->assertJsonPath('data.person.id', $a->id);
        $this->api('POST', '/people/resolve', ['name' => 'Al McNicoll', 'city' => 'Mars'])->assertJsonPath('status', 'not_found');
    }

    public function test_no_match_suggests_the_closest_names(): void
    {
        $this->person('Al', 'McNicoll');
        $this->person('Alan', 'Nicholl');

        $r = $this->api('POST', '/people/resolve', ['name' => 'Alan McNichol']);

        $this->assertEnvelope($r, 'not_found')->assertOk();
        $this->assertStringContainsString("I couldn't find anyone called 'Alan McNichol'", $r->json('message'));

        $r = $this->api('POST', '/people/resolve', ['name' => 'Al McNichol']);
        $r->assertJsonPath('status', 'not_found');
        $this->assertNotEmpty($r->json('data.suggestions'));

        $this->api('POST', '/entries', ['type' => 'note', 'people' => ['Al McNichol'], 'body' => 'x'])->assertStatus(404);
    }

    public function test_several_people_are_resolved_before_anything_is_written(): void
    {
        $this->twoAls();
        $this->person('Jo', 'Bloggs');
        $this->person('Pat', 'Quillen');

        $r = $this->api('POST', '/entries', ['type' => 'prayer_need', 'people' => ['Al McNicoll', 'Jo Bloggs', 'Pat Quillan'], 'title' => 'Family illness', 'body' => 'x']);

        $this->assertEnvelope($r, 'ambiguous')->assertStatus(409);
        $this->assertDatabaseCount('prayer_needs', 0);
        $problems = collect($r->json('data.problems'))->keyBy('reference');
        $this->assertSame('ambiguous', $problems['Al McNicoll']['status']);
        $this->assertSame('not_found', $problems['Pat Quillan']['status']);
        $this->assertFalse($problems->has('Jo Bloggs'));
        $this->assertStringContainsString('Jo Bloggs was found', $r->json('message'));
    }

    public function test_a_lone_partial_match_is_used_but_flagged(): void
    {
        $this->person('Pat', 'Quillen');

        $r = $this->api('POST', '/people/resolve', ['name' => 'Pat Quill']);

        $r->assertJsonPath('status', 'ok');
        $this->assertStringContainsString('only a partial match', $r->json('warnings.0'));
    }

    public function test_unusable_references_are_rejected_clearly(): void
    {
        $this->api('POST', '/people/resolve', ['name' => ''])->assertStatus(422)->assertJsonPath('status', 'invalid');
        $this->api('POST', '/people/resolve', ['nmae' => 'Al'])->assertStatus(422);
        $this->api('POST', '/entries', ['type' => 'note', 'people' => [99999], 'body' => 'x'])->assertStatus(404);
    }
}
