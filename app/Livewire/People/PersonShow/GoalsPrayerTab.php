<?php

namespace App\Livewire\People\PersonShow;

use App\Actions\CreateEntry;
use App\Actions\EntryRules;
use App\Actions\RecordOutcome;
use App\Actions\ResolvePrayerNeed;
use App\Livewire\Concerns\CatchesDbErrors;
use App\Models\Goal;
use App\Models\Person;
use App\Models\PrayerNeed;
use Livewire\Component;

class GoalsPrayerTab extends Component
{
    use CatchesDbErrors;
    public Person $person;

    // Shared form state
    public string $formType = ''; // 'goal', 'prayer', 'resolve_goal', 'resolve_prayer', 'outcome'
    public ?int $editingId = null;
    public bool $showForm = false;

    // Shared fields
    public string $title = '';
    public string $body = '';
    public int $significance = 3;
    public string $date = '';

    // Goal-specific
    public ?string $targetDate = null;

    // Resolve goal
    public bool $goalComplete = false;
    public ?string $achievedAt = null;
    public string $outcomeTitle = '';
    public string $outcomeBody = '';

    // Resolve prayer
    public ?string $resolvedAt = null;
    public string $resolutionDetails = '';

    public function mount(Person $person): void
    {
        $this->person = $person;
        $this->date   = now()->format('Y-m-d');
    }

    public function openAddGoal(): void
    {
        $this->resetForm();
        $this->formType = 'goal';
        $this->showForm = true;
    }

    public function openAddPrayer(): void
    {
        $this->resetForm();
        $this->formType = 'prayer';
        $this->showForm = true;
    }

    public function editGoal(int $id): void
    {
        $goal = Goal::findOrFail($id);
        abort_if($goal->user_id !== auth()->id(), 403);

        $this->editingId   = $id;
        $this->formType    = 'goal';
        $this->title       = $goal->title;
        $this->body        = $goal->body;
        $this->significance = $goal->significance;
        $this->date        = $goal->date->format('Y-m-d');
        $this->targetDate  = $goal->target_date?->format('Y-m-d');
        $this->showForm    = true;
    }

    public function editPrayer(int $id): void
    {
        $need = PrayerNeed::findOrFail($id);
        abort_if($need->user_id !== auth()->id(), 403);

        $this->editingId    = $id;
        $this->formType     = 'prayer';
        $this->title        = $need->title ?? '';
        $this->body         = $need->body;
        $this->significance = $need->significance;
        $this->date         = $need->date->format('Y-m-d');
        $this->showForm     = true;
    }

    public function openResolveGoal(int $id): void
    {
        $this->resetForm();
        $this->editingId = $id;
        $this->formType  = 'resolve_goal';
        $this->showForm  = true;
    }

    public function openResolvePrayer(int $id): void
    {
        $this->resetForm();
        $this->editingId  = $id;
        $this->formType   = 'resolve_prayer';
        $this->resolvedAt = now()->format('Y-m-d');
        $this->showForm   = true;
    }

    public function save(): void
    {
        match ($this->formType) {
            'goal'           => $this->saveGoal(),
            'prayer'         => $this->savePrayer(),
            'resolve_goal'   => $this->resolveGoal(),
            'resolve_prayer' => $this->resolvePrayer(),
            default          => null,
        };
    }

    protected function saveGoal(): void
    {
        $this->validate(EntryRules::livewire('goal'));

        $data = [
            'title'       => $this->title,
            'body'        => $this->body,
            'significance' => $this->significance,
            'date'        => $this->date,
            'target_date' => $this->targetDate,
        ];

        if ($this->editingId) {
            $goal = Goal::findOrFail($this->editingId);
            abort_if($goal->user_id !== auth()->id(), 403);
            $goal->update($data + ['logged_at' => now()]);
        } else {
            (new CreateEntry)->execute(auth()->id(), 'goal', [$this->person->id], $data);
        }

        $verb = $this->editingId ? 'updated' : 'added';
        $this->resetForm();
        $this->dispatch('notify', message: "Goal {$verb}.");
    }

    protected function savePrayer(): void
    {
        $this->validate(EntryRules::livewire('prayer_need'));

        $data = [
            'title'       => $this->title ?: null,
            'body'        => $this->body,
            'significance' => $this->significance,
            'date'        => $this->date,
        ];

        if ($this->editingId) {
            $need = PrayerNeed::findOrFail($this->editingId);
            abort_if($need->user_id !== auth()->id(), 403);
            $need->update($data + ['logged_at' => now()]);
        } else {
            (new CreateEntry)->execute(auth()->id(), 'prayer_need', [$this->person->id], $data);
        }

        $verb = $this->editingId ? 'updated' : 'added';
        $this->resetForm();
        $this->dispatch('notify', message: "Prayer Need {$verb}.");
    }

    protected function resolveGoal(): void
    {
        $this->validate([
            'goalComplete'  => 'boolean',
            'achievedAt'    => 'nullable|required_if:goalComplete,true|date',
            'outcomeTitle'  => EntryRules::TITLE_REQUIRED,
            'outcomeBody'   => EntryRules::BODY,
            'significance'  => EntryRules::SIGNIFICANCE,
        ]);

        $goal = Goal::findOrFail($this->editingId);
        abort_if($goal->user_id !== auth()->id(), 403);

        (new RecordOutcome)->execute($goal, $this->outcomeTitle, $this->outcomeBody, $this->significance, $this->goalComplete, $this->achievedAt);

        $this->resetForm();
        $this->dispatch('notify', message: 'Outcome recorded.');
    }

    protected function resolvePrayer(): void
    {
        $this->validate([
            'resolvedAt'        => 'required|date',
            'resolutionDetails' => 'nullable|string',
        ]);

        $need = PrayerNeed::findOrFail($this->editingId);
        abort_if($need->user_id !== auth()->id(), 403);
        (new ResolvePrayerNeed)->resolve($need, $this->resolvedAt, $this->resolutionDetails ?: null);

        $this->resetForm();
        $this->dispatch('notify', message: 'Prayer Need resolved.');
    }

    public function deleteGoal(int $id): void
    {
        $goal = Goal::findOrFail($id);
        abort_if($goal->user_id !== auth()->id(), 403);
        $goal->delete();
        $this->dispatch('notify', message: 'Goal deleted.');
    }

    public function deletePrayer(int $id): void
    {
        $need = PrayerNeed::findOrFail($id);
        abort_if($need->user_id !== auth()->id(), 403);
        $need->delete();
        $this->dispatch('notify', message: 'Prayer Need deleted.');
    }

    public function resetForm(): void
    {
        $this->showForm          = false;
        $this->formType          = '';
        $this->editingId         = null;
        $this->title             = '';
        $this->body              = '';
        $this->significance      = 3;
        $this->date              = now()->format('Y-m-d');
        $this->targetDate        = null;
        $this->goalComplete      = false;
        $this->achievedAt        = null;
        $this->outcomeTitle      = '';
        $this->outcomeBody       = '';
        $this->resolvedAt        = null;
        $this->resolutionDetails = '';
    }

    public function render()
    {
        $activeGoals = $this->person->goals()->active()
            ->with('outcomes')
            ->orderByDesc('significance')
            ->orderBy('target_date')
            ->get();

        $resolvedGoals = $this->person->goals()->achieved()
            ->orderByDesc('achieved_at')
            ->get();

        $activePrayer = $this->person->prayerNeeds()->unresolved()
            ->orderByDesc('significance')
            ->orderByDesc('date')
            ->get();

        $resolvedPrayer = $this->person->prayerNeeds()->resolved()
            ->orderByDesc('resolved_at')
            ->get();

        return view('livewire.people.person-show.goals-prayer-tab', compact(
            'activeGoals', 'resolvedGoals', 'activePrayer', 'resolvedPrayer'
        ));
    }
}
