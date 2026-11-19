<?php

namespace Tests\Unit\Api;

use App\Services\Api\Narrator;
use PHPUnit\Framework\TestCase;

class NarratorTest extends TestCase
{
    private function al(string $born, array $extra = []): array
    {
        return $extra + ['id' => 5, 'display_name' => 'Al McNicoll', 'born' => $born, 'born_year_unknown' => false, 'cities' => [], 'relationships' => [], 'gender' => 'male'];
    }

    public function test_dates_and_plurals_and_lists(): void
    {
        $this->assertSame('13 Jul 1984', Narrator::date('1984-07-13'));
        $this->assertSame('13 Jul', Narrator::date('1984-07-13', true));
        $this->assertSame('1 prayer need', Narrator::plural(1, 'prayer need'));
        $this->assertSame('0 prayer needs', Narrator::plural(0, 'prayer need'));
        $this->assertSame('2 people', Narrator::plural(2, 'person', 'people'));
        $this->assertSame('', Narrator::join([]));
        $this->assertSame('A', Narrator::join(['A']));
        $this->assertSame('A and B', Narrator::join(['A', 'B']));
        $this->assertSame('A, B or C', Narrator::join(['A', 'B', 'C'], 'or'));
        $this->assertSame('Al McNicoll (13 Jul 1984)', Narrator::label($this->al('1984-07-13')));
        $this->assertSame('Al McNicoll', Narrator::label($this->al('1984-07-13', ['born' => null])));
    }

    public function test_ambiguous_message_for_a_write_names_the_best_distinguishing_field(): void
    {
        $candidates = [$this->al('1984-07-13'), $this->al('1991-02-02', ['id' => 31])];

        $this->assertSame(
            "There are two people called Al McNicoll in PastorEyes, so I haven't saved anything. "
            . 'Please say which one, for example by date of birth (13 Jul 1984 or 2 Feb 1991), or by passing their id.',
            Narrator::ambiguous('Al McNicoll', $candidates, ['born', 'cities'], true, 2)
        );
    }

    public function test_ambiguous_message_for_a_read_and_other_fields(): void
    {
        $candidates = [$this->al('1984-07-13', ['cities' => ['Reading']]), $this->al('1984-07-13', ['id' => 31, 'cities' => ['Leeds']])];

        $msg = Narrator::ambiguous('Al McNicoll', $candidates, ['cities'], false, 2);

        $this->assertStringContainsString("I haven't picked one", $msg);
        $this->assertStringContainsString('by town or city (Reading or Leeds)', $msg);
    }

    public function test_ambiguous_with_nothing_to_tell_them_apart_says_so(): void
    {
        $msg = Narrator::ambiguous('Al McNicoll', [$this->al('1984-07-13'), $this->al('1984-07-13', ['id' => 6])], [], true, 3);

        $this->assertStringContainsString('There are three people', $msg);
        $this->assertStringContainsString("can't tell them apart", $msg);
        $this->assertStringContainsString('Showing the first 2 of 3.', $msg);
    }

    public function test_not_found_with_and_without_suggestions(): void
    {
        $this->assertSame("I couldn't find anyone called 'Zed'.", Narrator::notFound('Zed', []));
        $this->assertSame("I couldn't find anyone called 'Alan McNichol'. The closest names are Al McNicoll and Alan Nicholl. Did you mean one of them?",
            Narrator::notFound('Alan McNichol', ['Al McNicoll', 'Alan Nicholl']));
        $this->assertStringContainsString('The closest name is Al McNicoll. Did you mean them?', Narrator::notFound('Al', ['Al McNicoll']));
    }

    public function test_problems_lists_every_unusable_reference_at_once(): void
    {
        $msg = Narrator::problems([
            ['reference' => 'Al McNicoll', 'status' => 'ambiguous', 'candidates' => [$this->al('1984-07-13'), $this->al('1991-02-02')], 'distinguishing' => ['born']],
            ['reference' => 'Jo Bloggs', 'status' => 'ok', 'person' => ['display_name' => 'Jo Bloggs', 'born' => null]],
            ['reference' => 'Pat Quill', 'status' => 'not_found', 'suggestions' => [['display_name' => 'Pat Quillen']]],
        ], true);

        $this->assertSame(
            "I haven't saved anything. Of the three people: Al McNicoll matches two people (13 Jul 1984 or 2 Feb 1991); Jo Bloggs was found; "
            . "I couldn't find anyone called Pat Quill (did you mean Pat Quillen?). Please correct those two and resend.",
            $msg
        );
    }

    public function test_person_summary_uses_names_and_dates_never_pronouns(): void
    {
        $msg = Narrator::personSummary($this->al('1984-07-13', [
            'age' => 42, 'relationships' => ['spouse of Caroline McNicoll'], 'active_prayer_needs' => 1, 'open_tasks' => 0, 'last_entry_date' => '2026-11-02',
        ]));

        $this->assertSame('Al McNicoll (born 13 Jul 1984, aged 42), spouse of Caroline McNicoll. 1 active prayer need, 0 open tasks. Last entry: 2 Nov 2026.', $msg);
        $this->assertDoesNotMatchRegularExpression('/\b(he|she|his|her|him)\b/i', $msg);
        $this->assertStringContainsString('No entries yet.', Narrator::personSummary($this->al('1984-07-13')));
    }

    public function test_person_briefing(): void
    {
        $msg = Narrator::personBriefing([
            'display_name' => 'Al McNicoll', 'age' => 42, 'relationships' => ['spouse of Caroline McNicoll'], 'cities' => ['Reading'],
            'last_entry' => ['type_label' => 'Note', 'date' => '2026-11-02', 'title' => 'Hospital visit'],
            'active_prayer_needs' => [['title' => 'Hip operation', 'significance' => 4, 'date' => '2026-10-20']],
            'active_goals' => [['title' => 'Join a small group', 'target_date' => '2026-12-01']],
            'open_tasks' => 2, 'next_key_date' => ['label' => 'Birthday', 'days_until' => 237],
        ]);

        $this->assertSame(
            "Al McNicoll, 42, spouse of Caroline McNicoll, lives in Reading. Last contact was a note on 2 Nov 2026 ('Hospital visit'). "
            . '1 active prayer need: Hip operation (significance 4, since 20 Oct 2026). 1 goal in progress: \'Join a small group\' (target 1 Dec 2026). '
            . '2 open tasks. Birthday in 237 days.',
            $msg
        );
        $this->assertStringContainsString('There are no entries about Jo Bloggs yet.', Narrator::personBriefing(['display_name' => 'Jo Bloggs']));
    }

    public function test_entry_added_and_dry_run(): void
    {
        $people = [$this->al('1984-07-13'), ['display_name' => 'Caroline McNicoll', 'born' => null]];

        $this->assertSame("Added a Note 'Hospital visit' dated 18 Nov 2026 for Al McNicoll (13 Jul 1984) and Caroline McNicoll.",
            Narrator::entryAdded('Note', 'Hospital visit', '18 Nov 2026', $people, false));
        $this->assertSame('Would add an Outcome dated 18 Nov 2026 for Al McNicoll (13 Jul 1984) and Caroline McNicoll.',
            Narrator::entryAdded('Outcome', null, '18 Nov 2026', $people, true));
    }

    public function test_listing_and_briefing_counts(): void
    {
        $this->assertSame("Found 2 people matching 'mcnicoll'.", Narrator::people('mcnicoll', 2));
        $this->assertSame("Found 1 person matching 'al'.", Narrator::people('al', 1));
        $this->assertSame('No people found.', Narrator::people('', 0));
        $this->assertSame('Found 3 entries for Al McNicoll. Showing 1; use page to see more.', Narrator::entries(3, 1, ['person' => 'Al McNicoll']));
        $this->assertSame('No active tasks found.', Narrator::tasks(0, 'active'));
        $this->assertSame('Found 1 completed task.', Narrator::tasks(1, 'completed'));
        $this->assertSame(
            'Needing attention: 1 key date in the next 30 days, 0 active prayer needs, 2 tasks due or overdue and 0 goals with an approaching target date. 3 contact sync differences waiting for review in the app.',
            Narrator::briefing(['key_dates' => 1, 'prayer_needs' => 0, 'tasks' => 2, 'goals' => 0, 'sync_differences' => 3], 30)
        );
    }
}
