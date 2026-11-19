<?php

namespace Tests\Feature\Api;

use App\Models\Person;
use App\Models\Relationship;
use App\Models\RelationshipType;
use App\Models\Task;

class PeopleTasksTest extends ApiTestCase
{
    // ---- People --------------------------------------------------------------

    public function test_creating_a_person_with_a_birthday_and_address(): void
    {
        $r = $this->api('POST', '/people', [
            'first_name' => 'Jo', 'last_name' => 'Bloggs', 'gender' => 'female', 'born' => '1990-05-04', 'about' => 'Met at the Tuesday group.',
            'addresses' => [['line_1' => '2 Mill Lane', 'city' => 'Leeds', 'postcode' => 'LS1 1AA']],
        ]);

        $this->assertEnvelope($r, 'ok')->assertCreated();
        $this->assertSame('Added Jo Bloggs (4 May 1990).', $r->json('message'));

        $id = $r->json('data.id');
        $show = $this->api('GET', "/people/{$id}?include=*");
        $show->assertJsonPath('data.born.date', '1990-05-04')->assertJsonPath('data.about', 'Met at the Tuesday group.')
            ->assertJsonPath('data.addresses.0.city', 'Leeds')->assertJsonPath('data.names.0.first_name', 'Jo')
            ->assertJsonPath('data.key_dates.0.type', 'birthday');
    }

    public function test_a_person_needs_a_name_and_a_dry_run_creates_nobody(): void
    {
        $this->api('POST', '/people', ['gender' => 'male'])->assertStatus(422);
        $this->api('POST', '/people?dry_run=1', ['first_name' => 'Jo'])->assertOk();
        $this->assertDatabaseCount('persons', 0);
    }

    public function test_a_duplicate_person_needs_confirmation(): void
    {
        $this->person('Al', 'McNicoll', '1984-07-13');

        $r = $this->api('POST', '/people', ['first_name' => 'Al', 'last_name' => 'McNicoll', 'born' => '1984-07-13']);
        $this->assertEnvelope($r, 'possible_duplicate')->assertStatus(409);
        $this->assertStringContainsString('Someone called Al McNicoll (13 Jul 1984) already exists', $r->json('message'));
        $this->assertSame(1, Person::count());

        // A clearly different birth date is not a duplicate; a confirmed one goes ahead
        $this->api('POST', '/people', ['first_name' => 'Al', 'last_name' => 'McNicoll', 'born' => '1991-02-02'])->assertCreated();
        $this->api('POST', '/people', ['first_name' => 'Al', 'last_name' => 'McNicoll', 'born' => '1984-07-13', 'confirm_duplicate' => true])->assertCreated();
        $this->assertSame(3, Person::count());
    }

    public function test_updating_names_addresses_birthday_and_death(): void
    {
        $p = $this->person('Al', 'McNicoll');

        $this->api('PATCH', "/people/{$p->id}", ['gender' => 'male', 'about' => 'Bio', 'date_of_death' => '2025-03-01'])->assertOk();
        $this->assertSame('male', $p->fresh()->gender);
        $this->api('GET', "/people/{$p->id}?include=about,key_dates")->assertJsonPath('data.about', 'Bio')->assertJsonPath('data.died.date', '2025-03-01')
            ->assertJsonPath('data.key_dates.0.recurrence_count', 3);
        $this->api('PATCH', "/people/{$p->id}", ['date_of_death' => null])->assertOk();
        $this->api('GET', "/people/{$p->id}")->assertJsonPath('data.died', null);

        $this->api('PUT', "/people/{$p->id}/birthday", ['date' => '1984-07-13'])->assertOk();
        $this->api('PUT', "/people/{$p->id}/birthday", ['date' => '1985-01-01'])->assertOk();
        $this->assertSame('1985-01-01', $this->api('GET', "/people/{$p->id}")->json('data.born.date'));          // replaced, not duplicated

        $n = $this->api('POST', "/people/{$p->id}/names", ['type' => 'married', 'first_name' => 'Alan', 'last_name' => 'Jones', 'is_primary' => true]);
        $n->assertCreated();
        $this->assertSame('Alan Jones', $this->api('GET', "/people/{$p->id}")->json('data.display_name'));
        $this->api('POST', "/people/{$p->id}/names", ['type' => 'married'])->assertStatus(422);

        $this->api('POST', "/people/{$p->id}/addresses", ['city' => 'Reading', 'is_current' => true])->assertCreated();
        $this->api('POST', "/people/{$p->id}/addresses", ['city' => 'Leeds'])->assertCreated();        // becomes current, the previous one is not
        $addresses = $this->api('GET', "/people/{$p->id}?include=addresses")->json('data.addresses');
        $this->assertSame(['Leeds' => true, 'Reading' => false], collect($addresses)->pluck('is_current', 'city')->all());
        $this->api('POST', "/people/{$p->id}/addresses", ['is_current' => true])->assertStatus(422);
    }

    public function test_listing_people_with_filters_and_a_person_by_name(): void
    {
        $al = $this->person('Al', 'McNicoll', '1984-07-13');
        $cz = $this->person('Caroline', 'McNicoll', '1986-03-02');
        $this->api('POST', '/entries', ['type' => 'prayer_need', 'people' => [$al->id], 'title' => 't', 'body' => 'b', 'date' => '2026-11-02']);

        $r = $this->api('GET', '/people?q=mcnicoll');
        $this->assertSame('Found 2 people matching \'mcnicoll\'.', $r->json('message'));
        $this->assertSame(['Al McNicoll', 'Caroline McNicoll'], collect($r->json('data'))->pluck('display_name')->all());
        $r->assertJsonPath('data.0.active_prayer_needs', 1)->assertJsonPath('data.0.last_entry_date', '2026-11-02')->assertJsonPath('data.0.age', 42);

        $this->assertSame([$al->id], collect($this->api('GET', '/people?has_active_prayer_needs=true')->json('data'))->pluck('id')->all());
        $this->assertSame([$cz->id], collect($this->api('GET', '/people?has_active_prayer_needs=false&born=1986')->json('data'))->pluck('id')->all());
        $this->assertSame($al->id, $this->api('GET', '/people?sort=recent')->json('data.0.id'));

        $by = $this->api('GET', '/people/by?name=Al McNicoll&born=1984');
        $by->assertOk()->assertJsonPath('status', 'ok')->assertJsonPath('data.id', $al->id);
        $this->assertEnvelope($this->api('GET', '/people/by?name=McNicoll'), 'ambiguous')->assertOk();
        $this->api('GET', '/people/9999')->assertStatus(404);
        $this->api('GET', "/people/{$al->id}?include=bogus")->assertStatus(422);
    }

    public function test_the_person_briefing_is_a_plain_english_summary(): void
    {
        $al = $this->person('Al', 'McNicoll', '1984-07-13');
        $this->api('POST', '/entries', ['type' => 'prayer_need', 'people' => [$al->id], 'title' => 'Hip operation', 'body' => 'x', 'date' => '2026-10-20', 'significance' => 4]);
        $this->api('POST', '/entries', ['type' => 'goal', 'people' => [$al->id], 'title' => 'Join a small group', 'body' => 'x', 'target_date' => '2026-12-01']);

        $r = $this->api('GET', "/people/{$al->id}/briefing");

        $msg = $r->json('message');
        $this->assertStringContainsString('Al McNicoll, 42', $msg);
        $this->assertStringContainsString("1 active prayer need: Hip operation (significance 4, since 20 Oct 2026)", $msg);
        $this->assertStringContainsString("1 goal in progress: 'Join a small group' (target 1 Dec 2026)", $msg);
        $this->assertStringContainsString('Birthday in', $msg);
        $r->assertJsonPath('data.person.id', $al->id)->assertJsonCount(1, 'data.active_prayer_needs');
    }

    public function test_the_whole_briefing_is_the_dashboard(): void
    {
        $al = $this->person('Al', 'McNicoll', now()->subYears(30)->addDays(5)->toDateString());
        $this->api('POST', '/entries', ['type' => 'prayer_need', 'people' => [$al->id], 'title' => 'Hip', 'body' => 'x', 'significance' => 5]);
        $this->api('POST', '/tasks', ['title' => 'Overdue call', 'due_date' => now()->subDay()->toDateString(), 'people' => [$al->id]]);

        $r = $this->api('GET', '/briefing?days=30');

        $r->assertOk()->assertJsonCount(1, 'data.upcoming_key_dates')->assertJsonCount(1, 'data.active_prayer_needs')->assertJsonCount(1, 'data.tasks_due_or_overdue');
        $this->assertStringContainsString('1 key date in the next 30 days', $r->json('message'));
        $this->assertTrue($r->json('data.tasks_due_or_overdue.0.overdue'));
        $this->api('GET', '/briefing?days=999')->assertStatus(422);
    }

    public function test_search(): void
    {
        $al = $this->person('Al', 'McNicoll');
        $this->api('POST', '/entries', ['type' => 'note', 'people' => [$al->id], 'body' => 'Loves gardening']);

        $this->api('GET', '/search?q=nicoll')->assertJsonCount(1, 'data.people');
        $this->api('GET', '/search?q=gardening&scope=entries')->assertJsonCount(1, 'data.entries');
        $all = $this->api('GET', '/search?q=al&scope=all');
        $all->assertJsonCount(1, 'data.people');
        $this->api('GET', '/search')->assertStatus(422);
    }

    // ---- Tasks ---------------------------------------------------------------

    public function test_task_lifecycle(): void
    {
        $al = $this->person('Al', 'McNicoll');

        $r = $this->api('POST', '/tasks', ['title' => 'Call about the lift', 'narrative' => 'Ask about Tuesday', 'due_date' => '2026-11-25', 'people' => ['Al McNicoll']]);
        $r->assertCreated()->assertJsonPath('data.people.0.id', $al->id);
        $this->assertSame('api', Task::first()->source);
        $this->api('POST', '/tasks', ['title' => 'No people needed', 'due_date' => '2026-11-26'])->assertCreated();
        $this->api('POST', '/tasks', ['due_date' => '2026-11-26'])->assertStatus(422);
        $this->api('POST', '/tasks', ['title' => 'x'])->assertStatus(422);

        $this->api('GET', '/tasks')->assertJsonCount(2, 'data')->assertJsonPath('data.0.title', 'Call about the lift');   // soonest first
        $this->api('GET', '/tasks?person=' . $al->id)->assertJsonCount(1, 'data');
        $this->api('GET', '/tasks?due_after=2026-11-26')->assertJsonCount(1, 'data');

        $this->api('POST', '/tasks/1/complete')->assertOk()->assertJsonPath('data.status', 'completed');
        $this->api('GET', '/tasks')->assertJsonCount(1, 'data');
        $this->api('GET', '/tasks?status=completed')->assertJsonCount(1, 'data');
        $this->api('GET', '/tasks?status=all')->assertJsonCount(2, 'data');
        $again = $this->api('POST', '/tasks/1/complete');
        $this->assertNotEmpty($again->json('warnings'));
        $this->api('POST', '/tasks/1/reopen')->assertJsonPath('data.status', 'active');

        $this->api('PATCH', '/tasks/1', ['title' => 'Call tomorrow', 'people' => []])->assertOk()->assertJsonPath('data.title', 'Call tomorrow')->assertJsonCount(0, 'data.people');
        $this->api('PATCH', '/tasks/1', ['title' => ''])->assertStatus(422);
        $this->api('GET', '/tasks/1')->assertStatus(404);        // there is no single-task read: GET /tasks lists them
        $this->api('POST', '/tasks/999/complete')->assertStatus(404);
    }

    public function test_deleting_a_task_is_two_step(): void
    {
        $del = $this->tokenFor($this->user, 'delete');
        $this->api('POST', '/tasks', ['title' => 'Bin it', 'due_date' => '2026-11-26']);

        $first = $this->api('DELETE', '/tasks/1', token: $del);
        $this->assertEnvelope($first, 'needs_confirmation');
        $this->assertDatabaseCount('tasks', 1);

        $this->api('DELETE', '/tasks/1?confirmation_token=' . $first->json('data.confirmation_token'), token: $del)->assertOk();
        $this->assertDatabaseCount('tasks', 0);
    }

    // ---- Relationships -------------------------------------------------------

    private function types(): array
    {
        return [
            'father'  => RelationshipType::create(['name' => 'father', 'inverse_name' => 'child', 'is_directional' => true, 'is_preset' => true]),
            'parent'  => RelationshipType::create(['name' => 'parent', 'inverse_name' => 'child', 'is_directional' => true, 'is_preset' => true]),
            'spouse'  => RelationshipType::create(['name' => 'spouse', 'is_directional' => false, 'is_preset' => true]),
            'mentor'  => RelationshipType::create(['name' => 'mentor', 'inverse_name' => 'mentee', 'is_directional' => true, 'is_preset' => true]),
        ];
    }

    public function test_a_relationship_works_out_its_direction_from_the_type_given(): void
    {
        $types = $this->types();
        $jim = $this->person('Jim', 'McNicoll');
        $al  = $this->person('Al', 'McNicoll', '1984-07-13');

        $this->api('GET', '/relationship-types')->assertOk()->assertJsonCount(4, 'data');

        // Said from the father's side: Jim is father of Al
        $r = $this->api('POST', "/people/{$jim->id}/relationships", ['type' => 'father of', 'other' => ['name' => 'Al McNicoll']]);
        $r->assertCreated();
        $this->assertSame('Recorded: Jim McNicoll is father of Al McNicoll (so Al McNicoll is child of Jim McNicoll).', $r->json('message'));
        $rel = Relationship::first();
        $this->assertSame([$jim->id, $al->id, $types['father']->id], [$rel->person_id, $rel->related_person_id, $rel->relationship_type_id]);

        // Said from the other end with the inverse name is the same relationship: recorded once
        $same = $this->api('POST', "/people/{$al->id}/relationships", ['type' => 'father', 'other' => $jim->id]);
        $this->assertSame(1, Relationship::count());
        $this->assertNotEmpty($same->json('warnings'));

        // Mentor: the inverse name puts the posting person on the "to" end
        $this->api('POST', "/people/{$al->id}/relationships", ['type' => 'mentee', 'other' => $jim->id])->assertCreated();
        $mentor = Relationship::where('relationship_type_id', $types['mentor']->id)->first();
        $this->assertSame([$jim->id, $al->id], [$mentor->person_id, $mentor->related_person_id]);

        // Shown from each side, in the person's own role
        $this->assertContains('father of Al McNicoll', $this->api('GET', "/people/{$jim->id}")->json('data.relationship_labels'));
        $this->assertContains('child of Jim McNicoll', $this->api('GET', "/people/{$al->id}")->json('data.relationship_labels'));
    }

    public function test_relationship_type_and_person_problems_are_reported(): void
    {
        $this->types();
        $jim = $this->person('Jim', 'McNicoll');
        $al  = $this->person('Al', 'McNicoll');

        $this->api('POST', "/people/{$jim->id}/relationships", ['type' => 'nemesis', 'other' => $al->id])->assertStatus(422);
        $this->api('POST', "/people/{$jim->id}/relationships", ['type' => 'child', 'other' => $al->id])->assertStatus(422);   // fits father and parent: ask, do not guess
        $this->api('POST', "/people/{$jim->id}/relationships", ['type' => 'spouse', 'other' => $jim->id])->assertStatus(422);
        $this->api('POST', "/people/{$jim->id}/relationships", ['type' => 'spouse', 'other' => 'Nobody Here'])->assertStatus(404);
        $this->api('POST', "/people/{$jim->id}/relationships?dry_run=true", ['type' => 'spouse', 'other' => $al->id])->assertOk();
        $this->assertSame(0, Relationship::count());

        $first = $this->api('POST', "/people/{$jim->id}/relationships", ['type' => 'spouse', 'other' => $al->id]);
        $first->assertCreated();
        $del = $this->tokenFor($this->user, 'delete');
        $ask = $this->api('DELETE', '/relationships/1', token: $del);
        $this->assertStringContainsString('Neither person is deleted', $ask->json('message'));
        $this->api('DELETE', '/relationships/1?confirmation_token=' . $ask->json('data.confirmation_token'), token: $del)->assertOk();
        $this->assertSame(0, Relationship::count());
        $this->assertSame(2, Person::count());
    }
}
