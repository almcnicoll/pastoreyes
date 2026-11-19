<?php

namespace Tests\Feature\Api;

use App\Models\Goal;
use App\Models\KeyDate;
use App\Models\Note;
use App\Models\Outcome;
use App\Models\PrayerNeed;
use Illuminate\Support\Facades\DB;

class EntriesTest extends ApiTestCase
{
    public function test_creating_a_note_defaults_date_and_significance_and_marks_the_source(): void
    {
        $al = $this->person('Al', 'McNicoll', '1984-07-13');
        $cz = $this->person('Caroline', 'McNicoll');

        $r = $this->api('POST', '/entries', ['type' => 'note', 'people' => ['Al McNicoll', $cz->id], 'title' => 'Hospital visit', 'body' => 'Visited on the ward.']);

        $this->assertEnvelope($r, 'ok')->assertCreated()->assertJsonPath('data.entry_id', 'note_1');
        $this->assertStringContainsString("Added a Note 'Hospital visit'", $r->json('message'));
        $this->assertStringContainsString('Al McNicoll (13 Jul 1984) and Caroline McNicoll', $r->json('message'));

        $note = Note::first();
        $this->assertSame(3, $note->significance);
        $this->assertSame('api', $note->source);
        $this->assertSame('Test token', $note->source_token_name);
        $this->assertSame([$al->id => true, $cz->id => false], $note->persons->mapWithKeys(fn($p) => [$p->id => (bool) $p->pivot->is_primary])->all());  // first is primary

        // Stored encrypted, returned decrypted
        $this->assertStringNotContainsString('Visited', DB::table('notes')->value('body'));
        $this->api('GET', '/entries/note_1')->assertJsonPath('data.body', 'Visited on the ward.');
    }

    public function test_validation_matches_the_ui_and_unknown_fields_are_refused(): void
    {
        $al = $this->person('Al', 'McNicoll');
        $make = fn(array $extra) => $this->api('POST', '/entries', $extra + ['type' => 'note', 'people' => [$al->id], 'body' => 'x']);

        $this->assertEnvelope($make(['type' => 'goal', 'body' => 'x']), 'invalid')->assertStatus(422);               // a goal needs a title
        $make(['significance' => 6])->assertStatus(422);
        $make(['date' => 'next friday'])->assertStatus(422);
        $make(['title' => str_repeat('a', 501)])->assertStatus(422);
        $make(['body' => ''])->assertStatus(422);
        $make(['people' => []])->assertStatus(422);
        $make(['significane' => 4])->assertStatus(422)->assertJsonPath('data.errors.0.code', 'unknown');                // typo not silently ignored
        $make(['target_date' => '2026-12-01'])->assertStatus(422);                                                     // goal-only field on a note
        $this->api('POST', '/entries', ['type' => 'outcome', 'people' => [$al->id], 'body' => 'x', 'title' => 'x'])->assertStatus(422);
        $this->api('POST', '/entries', ['type' => 'nonsense', 'people' => [$al->id]])->assertStatus(422);
        $this->assertDatabaseCount('notes', 0);

        $r = $this->api('POST', '/entries', ['type' => 'goal', 'people' => [$al->id], 'body' => 'x', 'date' => 'bad']);
        $this->assertStringNotContainsString('"x"', $r->json('message'));      // never echoes entry text
    }

    public function test_dry_run_does_everything_but_write(): void
    {
        $this->person('Al', 'McNicoll', '1984-07-13');

        $r = $this->api('POST', '/entries?dry_run=true', ['type' => 'note', 'people' => ['Al McNicoll'], 'body' => 'Coffee']);

        $r->assertOk()->assertJsonPath('meta.dry_run', true);
        $this->assertStringStartsWith('Would add a Note', $r->json('message'));
        $this->assertDatabaseCount('notes', 0);

        $this->api('POST', '/tasks', ['title' => 't', 'due_date' => '2026-12-01', 'dry_run' => true])->assertOk()->assertJsonPath('meta.dry_run', true);
        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_idempotency_key_replays_the_original_response_and_writes_once(): void
    {
        $al   = $this->person('Al', 'McNicoll');
        $body = ['type' => 'note', 'people' => [$al->id], 'body' => 'Once only'];

        $first  = $this->api('POST', '/entries', $body, headers: ['Idempotency-Key' => 'abc-123']);
        $second = $this->api('POST', '/entries', array_reverse($body, true), headers: ['Idempotency-Key' => 'abc-123']);   // same request, different key order

        $first->assertCreated();
        $second->assertCreated()->assertHeader('Idempotency-Replayed', 'true');
        $this->assertSame($first->json('data'), $second->json('data'));
        $this->assertDatabaseCount('notes', 1);

        // A different body under the same key is refused, and a different key is a new request
        $this->api('POST', '/entries', ['body' => 'Different'] + $body, headers: ['Idempotency-Key' => 'abc-123'])->assertStatus(422);
        $this->assertDatabaseCount('notes', 1);

        // The stored response is encrypted at rest
        $this->assertStringNotContainsString('McNicoll', DB::table('api_idempotency_keys')->value('response'));
    }

    public function test_a_failed_request_is_not_remembered_by_its_idempotency_key(): void
    {
        $al = $this->person('Al', 'McNicoll');

        $this->api('POST', '/entries', ['type' => 'goal', 'people' => [$al->id], 'body' => 'x'], headers: ['Idempotency-Key' => 'k1'])->assertStatus(422);
        $this->api('POST', '/entries', ['type' => 'goal', 'people' => [$al->id], 'body' => 'x', 'title' => 'Fixed'], headers: ['Idempotency-Key' => 'k1'])->assertCreated();
    }

    public function test_a_probable_duplicate_needs_an_explicit_override(): void
    {
        $al   = $this->person('Al', 'McNicoll');
        $body = ['type' => 'prayer_need', 'people' => [$al->id], 'title' => 'Hip operation', 'body' => 'Operation on 3 Dec'];

        $this->api('POST', '/entries', $body)->assertCreated();

        $r = $this->api('POST', '/entries', ['body' => 'operation ON 3 dec', 'title' => 'hip OPERATION'] + $body);
        $this->assertEnvelope($r, 'possible_duplicate')->assertStatus(409);
        $this->assertDatabaseCount('prayer_needs', 1);

        $this->api('POST', '/entries', $body + ['confirm_duplicate' => true])->assertCreated();
        $this->assertDatabaseCount('prayer_needs', 2);
        $this->api('POST', '/entries', ['body' => 'A different matter'] + $body)->assertCreated();    // different text is not a duplicate
    }

    public function test_key_dates_goals_and_prayer_needs_have_their_own_fields(): void
    {
        $al = $this->person('Al', 'McNicoll');

        $this->api('POST', '/entries', ['type' => 'key_date', 'people' => [$al->id], 'key_date_type' => 'wedding_anniversary', 'date' => '2010-05-01', 'label' => 'Wedding'])->assertCreated();
        $kd = KeyDate::first();
        $this->assertTrue($kd->is_recurring);
        $this->assertSame('wedding_anniversary', $kd->type);

        $this->api('POST', '/entries', ['type' => 'key_date', 'people' => [$al->id], 'key_date_type' => 'nonsense', 'date' => '2010-05-01'])->assertStatus(422);
        $this->api('POST', '/entries', ['type' => 'key_date', 'people' => [$al->id], 'date' => '2010-05-01'])->assertStatus(422);           // type required
        $this->api('POST', '/entries', ['type' => 'key_date', 'people' => [$al->id], 'key_date_type' => 'death', 'date' => '2020-01-01', 'recurrence_count' => 3])->assertCreated();
        $this->assertSame(3, KeyDate::where('type', 'death')->value('recurrence_count'));

        $this->api('POST', '/entries', ['type' => 'goal', 'people' => [$al->id], 'title' => 'Join a small group', 'body' => 'x', 'target_date' => '2026-12-01'])->assertCreated();
        $this->assertSame('2026-12-01', Goal::first()->target_date->toDateString());
    }

    public function test_resolving_and_reopening_a_prayer_need(): void
    {
        $al = $this->person('Al', 'McNicoll');
        $this->api('POST', '/entries', ['type' => 'prayer_need', 'people' => [$al->id], 'title' => 'Hip operation', 'body' => 'x']);

        $r = $this->api('POST', '/entries/prayer_need_1/resolve', ['resolved_at' => '2026-11-18', 'resolution_details' => 'Surgery went well.']);
        $r->assertOk()->assertJsonPath('data.state', 'resolved')->assertJsonPath('data.resolution_details', 'Surgery went well.');
        $this->assertStringContainsString("Marked the Prayer Need 'Hip operation' about Al McNicoll", $r->json('message'));
        $this->assertNotNull(PrayerNeed::first()->resolved_at);

        $this->api('GET', '/entries?type=prayer_need&active=true')->assertJsonCount(0, 'data');

        $this->api('POST', '/entries/prayer_need_1/reopen')->assertOk()->assertJsonPath('data.state', 'active');
        $this->assertNull(PrayerNeed::first()->resolution_details);
        $this->api('GET', '/entries?type=prayer_need&active=true')->assertJsonCount(1, 'data');

        $this->api('POST', '/entries/note_1/resolve')->assertStatus(404);
        $this->api('POST', '/entries', ['type' => 'goal', 'people' => [$al->id], 'title' => 'g', 'body' => 'x']);
        $this->api('POST', '/entries/goal_1/resolve')->assertStatus(422);
    }

    public function test_an_outcome_is_recorded_on_a_goal_for_its_people_and_can_achieve_it(): void
    {
        $al = $this->person('Al', 'McNicoll');
        $cz = $this->person('Caroline', 'McNicoll');
        $this->api('POST', '/entries', ['type' => 'goal', 'people' => [$al->id, $cz->id], 'title' => 'Join a small group', 'body' => 'x']);

        $r = $this->api('POST', '/entries/goal_1/outcomes', ['title' => 'Joined', 'body' => 'Went along on Tuesday', 'goal_complete' => true, 'achieved_at' => '2026-11-18']);

        $r->assertCreated()->assertJsonPath('data.goal_achieved', true);
        $outcome = Outcome::first();
        $this->assertSame(1, $outcome->goal_id);
        $this->assertSame([$al->id => true, $cz->id => false], $outcome->persons->mapWithKeys(fn($p) => [$p->id => (bool) $p->pivot->is_primary])->all());
        $this->assertSame('2026-11-18', Goal::first()->achieved_at->toDateString());
        $this->assertSame('api', $outcome->source);

        $this->api('POST', '/entries/goal_1/outcomes', ['body' => 'no title'])->assertStatus(422);
        $this->api('POST', '/entries/note_1/outcomes', ['title' => 't', 'body' => 'b'])->assertStatus(404);
    }

    public function test_listing_filters_and_paging(): void
    {
        $al = $this->person('Al', 'McNicoll');
        $jo = $this->person('Jo', 'Bloggs');
        foreach ([['2026-11-01', 'Al', 5], ['2026-11-05', 'Al', 2], ['2026-11-09', 'Jo', 4]] as $i => [$date, $who, $sig]) {
            $this->api('POST', '/entries', ['type' => 'note', 'people' => [$who === 'Al' ? $al->id : $jo->id], 'date' => $date, 'significance' => $sig, 'body' => "Body number {$i} needle"])->assertCreated();
        }
        $this->api('POST', '/entries', ['type' => 'prayer_need', 'people' => [$al->id], 'date' => '2026-11-03', 'body' => 'Hay fever', 'significance' => 3]);

        $ids = fn($r) => collect($r->json('data'))->pluck('entry_id')->all();

        $this->assertSame(['note_3', 'note_2', 'prayer_need_1', 'note_1'], $ids($this->api('GET', '/entries'))); // newest first
        $this->assertSame(['note_1', 'prayer_need_1', 'note_2', 'note_3'], $ids($this->api('GET', '/entries?sort=oldest')));
        $this->assertSame(['note_2', 'prayer_need_1', 'note_1'], $ids($this->api('GET', '/entries?person=' . $al->id)));
        $this->assertSame(['note_3'], $ids($this->api('GET', '/entries?person=' . urlencode(json_encode(['name' => 'Jo Bloggs'])))));
        $this->assertSame(['note_3', 'note_2', 'note_1'], $ids($this->api('GET', '/entries?type=note')));
        $this->assertSame(['note_3', 'prayer_need_1'], $ids($this->api('GET', '/entries?type=note,prayer_need&from=2026-11-03&to=2026-11-09&min_significance=3')));
        $this->assertSame(['note_3', 'note_1'], $ids($this->api('GET', '/entries?type[]=note&significance[]=4&significance[]=5')));
        $this->assertSame(['note_3'], $ids($this->api('GET', '/entries?type=note&per_page=1')));
        $page2 = $this->api('GET', '/entries?type=note&per_page=1&page=2');
        $this->assertSame(['note_2'], $ids($page2));
        $page2->assertJsonPath('meta.pagination.total', 3)->assertJsonPath('meta.pagination.next_page', 3);

        $search = $this->api('GET', '/entries?q=number 1 NEEDLE&type=note');
        $this->assertSame(['note_2'], $ids($search));

        $this->api('GET', '/entries?type=bogus')->assertStatus(422);
        $this->api('GET', '/entries?persn=1')->assertStatus(422);
    }

    public function test_listing_with_an_ambiguous_person_reports_it_with_a_200(): void
    {
        $this->person('Al', 'McNicoll', '1984-07-13');
        $this->person('Al', 'McNicoll', '1991-02-02');

        $this->assertEnvelope($this->api('GET', '/entries?person=Al McNicoll'), 'ambiguous')->assertOk();
    }

    public function test_text_search_stops_at_the_scan_cap_and_says_so(): void
    {
        $al = $this->person('Al', 'McNicoll');
        config(['api.text_search_scan_cap' => 2]);
        foreach ([1, 2, 3] as $i) {
            $this->api('POST', '/entries', ['type' => 'note', 'people' => [$al->id], 'date' => "2026-11-0{$i}", 'body' => "needle {$i}"]);
        }

        $r = $this->api('GET', '/entries?q=needle&type=note');

        $r->assertJsonPath('meta.truncated', true)->assertJsonCount(2, 'data');
        $this->assertStringContainsString('Only the newest 2 entries were scanned', $r->json('message'));
    }

    public function test_editing_an_entry_and_replacing_its_people(): void
    {
        $al = $this->person('Al', 'McNicoll');
        $jo = $this->person('Jo', 'Bloggs');
        $this->api('POST', '/entries', ['type' => 'note', 'people' => [$al->id], 'title' => 'Old', 'body' => 'Old body']);

        $r = $this->api('PATCH', '/entries/note_1', ['title' => 'New', 'significance' => 5, 'people' => [$jo->id, $al->id]]);

        $r->assertOk()->assertJsonPath('data.title', 'New')->assertJsonPath('data.body', 'Old body')->assertJsonPath('data.significance', 5);
        $this->assertSame([$jo->id, $al->id], collect($r->json('data.people'))->pluck('id')->all());
        $this->assertTrue($r->json('data.people.0.is_primary'));

        $this->api('PATCH', '/entries/note_1', ['title' => null])->assertOk()->assertJsonPath('data.title', null);
        $this->api('PATCH', '/entries/note_1', ['type' => 'goal'])->assertStatus(422);        // type cannot change
        $this->api('PATCH', '/entries/note_1', ['significance' => 9])->assertStatus(422);
        $this->api('PATCH', '/entries/note_1?dry_run=true', ['title' => 'Nope'])->assertOk();
        $this->assertNull(Note::first()->title);
    }

    public function test_deleting_is_two_step_with_a_single_use_token_and_the_ui_warning(): void
    {
        $al   = $this->person('Al', 'McNicoll');
        $del  = $this->tokenFor($this->user, 'delete');
        $this->api('POST', '/entries', ['type' => 'goal', 'people' => [$al->id], 'title' => 'Join a small group', 'body' => 'x']);
        $this->api('POST', '/entries/goal_1/outcomes', ['title' => 'o', 'body' => 'b']);

        $first = $this->api('DELETE', '/entries/goal_1', token: $del);
        $this->assertEnvelope($first, 'needs_confirmation')->assertStatus(409);
        $this->assertStringContainsString('Delete this Goal', $first->json('message'));
        $this->assertStringContainsString('Its 1 outcome is deleted too. This cannot be undone.', $first->json('message'));
        $this->assertDatabaseCount('goals', 1);
        $token = $first->json('data.confirmation_token');

        // Wrong token, wrong target and other tokens' confirmations do not work
        $this->api('DELETE', '/entries/goal_1?confirmation_token=bogus', token: $del)->assertStatus(422);
        $this->api('DELETE', '/entries/outcome_1?confirmation_token=' . $token, token: $del)->assertStatus(422);
        $this->assertDatabaseCount('goals', 1);

        $first = $this->api('DELETE', '/entries/goal_1', token: $del);
        $this->api('DELETE', '/entries/goal_1?confirmation_token=' . $first->json('data.confirmation_token'), token: $del)->assertOk();
        $this->assertDatabaseCount('goals', 0);
        $this->assertDatabaseCount('outcomes', 0);                       // outcomes go with their goal...
        $this->assertDatabaseCount('person_entry', 0);                   // ...and no person links are left behind

        $this->api('DELETE', '/entries/goal_1?confirmation_token=' . $first->json('data.confirmation_token'), token: $del)->assertStatus(404);
    }
}
