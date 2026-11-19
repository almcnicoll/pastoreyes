<?php

namespace Tests\Feature;

use App\Livewire\People\PersonShow\GoalsPrayerTab;
use App\Livewire\People\PersonShow\KeyDatesTab;
use App\Livewire\People\PersonShow\OverviewTab;
use App\Livewire\QuickAddEntry;
use App\Livewire\Tasks;
use App\Models\Goal;
use App\Models\KeyDate;
use App\Models\Note;
use App\Models\Outcome;
use App\Models\PrayerNeed;
use App\Models\Task;
use Livewire\Livewire;
use Tests\Feature\Api\ApiTestCase;

/**
 * The web UI and the API share their create/resolve/save logic (app/Actions). These check the UI
 * still goes through it correctly, so a change for one cannot quietly break the other.
 */
class UiSharedActionsTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->user);
    }

    public function test_quick_add_creates_an_entry_with_the_first_person_primary(): void
    {
        $al = $this->person('Al', 'McNicoll');
        $jo = $this->person('Jo', 'Bloggs');

        Livewire::test(QuickAddEntry::class)
            ->set('selectedPersonIds', [$al->id, $jo->id])->set('type', 'note')->set('body', 'Coffee')
            ->call('save')->assertHasNoErrors();

        $note = Note::first();
        $this->assertNull($note->title);
        $this->assertSame([$al->id => true, $jo->id => false], $note->persons->mapWithKeys(fn($p) => [$p->id => (bool) $p->pivot->is_primary])->all());
        $this->assertNull($note->source);       // the UI does not mark entries as API-made
    }

    public function test_quick_add_validates_like_the_api(): void
    {
        $al = $this->person('Al', 'McNicoll');

        Livewire::test(QuickAddEntry::class)->set('selectedPersonIds', [$al->id])->set('type', 'goal')->set('body', 'x')
            ->call('save')->assertHasErrors(['title']);                       // a goal needs a title
        Livewire::test(QuickAddEntry::class)->set('selectedPersonIds', [$al->id])->call('save')->assertHasErrors(['type', 'body']);
        Livewire::test(QuickAddEntry::class)->set('selectedPersonIds', [$al->id])->set('type', 'note')->set('body', 'x')->set('significance', 9)
            ->call('save')->assertHasErrors(['significance']);
        $this->assertSame(0, Note::count() + Goal::count());
    }

    public function test_goals_and_prayer_needs_are_added_edited_resolved_and_given_outcomes(): void
    {
        $al = $this->person('Al', 'McNicoll');
        $cz = $this->person('Caroline', 'McNicoll');
        $tab = Livewire::test(GoalsPrayerTab::class, ['person' => $al]);

        $tab->call('openAddGoal')->set('title', 'Join a small group')->set('body', 'Find one')->call('save')->assertHasNoErrors();
        $tab->call('openAddPrayer')->set('body', 'Hip operation')->call('save')->assertHasNoErrors();
        $tab->call('openAddGoal')->set('body', 'no title')->call('save')->assertHasErrors(['title']);
        $tab->call('resetForm');

        $goal = Goal::first();
        $goal->persons()->attach($cz->id, ['is_primary' => false]);       // a second person on the goal
        $this->assertTrue((bool) $goal->persons()->wherePivot('is_primary', true)->first()->is($al));

        // Resolve the prayer need
        $tab->call('openResolvePrayer', PrayerNeed::first()->id)->set('resolvedAt', '2026-11-18')->set('resolutionDetails', 'Went well')->call('save')->assertHasNoErrors();
        $need = PrayerNeed::first();
        $this->assertSame('2026-11-18', $need->resolved_at->toDateString());
        $this->assertSame('Went well', $need->resolution_details);

        // Record an outcome and achieve the goal: the outcome belongs to the goal's people, primary kept
        $tab->call('openResolveGoal', $goal->id)->set('outcomeTitle', 'Joined')->set('outcomeBody', 'Went along')->set('goalComplete', true)->set('achievedAt', '2026-11-18')
            ->call('save')->assertHasNoErrors();
        $outcome = Outcome::first();
        $this->assertSame($goal->id, $outcome->goal_id);
        $this->assertSame([$al->id => true, $cz->id => false], $outcome->persons->mapWithKeys(fn($p) => [$p->id => (bool) $p->pivot->is_primary])->all());
        $this->assertSame('2026-11-18', $goal->fresh()->achieved_at->toDateString());

        // Deleting the goal takes its outcome and every person link with it
        $tab->call('deleteGoal', $goal->id);
        $this->assertSame(0, Outcome::count());
        $this->assertSame(1, \DB::table('person_entry')->where('entryable_type', PrayerNeed::class)->count());
        $this->assertSame(0, \DB::table('person_entry')->whereIn('entryable_type', [Goal::class, Outcome::class])->count());
    }

    public function test_a_death_key_date_defaults_to_three_recurrences_in_the_key_dates_tab(): void
    {
        $al = $this->person('Al', 'McNicoll');

        Livewire::test(KeyDatesTab::class, ['person' => $al])
            ->call('openAdd')->set('type', 'death')->set('date', '2025-03-01')->call('save')->assertHasNoErrors();

        $kd = KeyDate::first();
        $this->assertSame([3, true], [$kd->recurrence_count, $kd->is_recurring]);
        $this->assertTrue($kd->persons->first()->is($al));

        Livewire::test(KeyDatesTab::class, ['person' => $al])->call('openAdd')->set('type', 'nonsense')->set('date', '2025-03-01')->call('save')->assertHasErrors(['type']);
    }

    public function test_tasks_are_added_and_edited_with_their_people(): void
    {
        $al = $this->person('Al', 'McNicoll');

        Livewire::test(Tasks::class)->call('openAdd')->set('title', 'Call about the lift')->set('dueDate', '2026-11-25')->set('selectedPersonIds', [$al->id])
            ->call('save')->assertHasNoErrors();
        $task = Task::first();
        $this->assertSame([$al->id], $task->persons->pluck('id')->all());

        Livewire::test(Tasks::class)->call('edit', $task->id)->set('title', 'Call tomorrow')->set('selectedPersonIds', [])->call('save')->assertHasNoErrors();
        $this->assertSame('Call tomorrow', $task->fresh()->title);
        $this->assertCount(0, $task->fresh()->persons);

        Livewire::test(Tasks::class)->call('openAdd')->set('title', '')->call('save')->assertHasErrors(['title']);
    }

    public function test_the_overview_tab_sets_changes_and_clears_birthday_and_death(): void
    {
        $al = $this->person('Al', 'McNicoll');

        $tab = Livewire::test(OverviewTab::class, ['person' => $al]);
        $tab->call('startEditing')->set('birthday', '1984-07-13')->set('birthdayYearUnknown', false)->set('deathDate', '2025-03-01')->call('save')->assertHasNoErrors();

        $this->assertSame(['birthday', 'death'], KeyDate::orderBy('id')->pluck('type')->all());
        $this->assertSame(3, KeyDate::where('type', 'death')->value('recurrence_count'));

        $tab->call('startEditing')->set('birthday', '1985-01-02')->set('deathDate', null)->call('save')->assertHasNoErrors();
        $this->assertSame(['birthday'], KeyDate::pluck('type')->all());
        $this->assertSame('1985-01-02', KeyDate::first()->date->toDateString());

        $tab->call('startEditing')->set('birthday', null)->call('save')->assertHasNoErrors();
        $this->assertSame(0, KeyDate::count());
    }
}
