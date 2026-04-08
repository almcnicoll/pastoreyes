<?php

namespace App\Livewire\People\PersonShow;

use App\Livewire\Concerns\CatchesDbErrors;
use App\Models\Goal;
use App\Models\Outcome;
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
        $this->validate([
            'title'       => 'required|string|max:500',
            'body'        => 'required|string',
            'significance' => 'required|integer|min:1|max:5',
            'date'        => 'required|date',
            'targetDate'  => 'nullable|date',
        ]);

        $data = [
            'user_id'     => auth()->id(),
            'title'       => $this->title,
            'body'        => $this->body,
            'significance' => $this->significance,
            'date'        => $this->date,
            'target_date' => $this->targetDate,
            'logged_at'   => now(),
        ];

        if ($this->editingId) {
            $goal = Goal::findOrFail($this->editingId);
            abort_if($goal->user_id !== auth()->id(), 403);
            $goal->update($data);
        } else {
            $goal = Goal::create($data);
            $goal->persons()->attach($this->person->id, ['is_primary' => true]);
        }

        $this->resetForm();
        $this->dispatch('notify', message: 'Goal saved.');
    }

    protected function savePrayer(): void
    {
        $this->validate([
            'title'       => 'nullable|string|max:500',
            'body'        => 'required|string',
            'significance' => 'required|integer|min:1|max:5',
            'date'        => 'required|date',
        ]);

        $data = [
            'user_id'     => auth()->id(),
            'title'       => $this->title ?: null,
            'body'        => $this->body,
            'significance' => $this->significance,
            'date'        => $this->date,
            'logged_at'   => now(),
        ];

        if ($this->editingId) {
            $need = PrayerNeed::findOrFail($this->editingId);
            abort_if($need->user_id !== auth()->id(), 403);
            $need->update($data);
        } else {
            $need = PrayerNeed::create($data);
            $need->persons()->attach($this->person->id, ['is_primary' => true]);
        }

        $this->resetForm();
        $this->dispatch('notify', message: 'Prayer need saved.');
    }

    protected function resolveGoal(): void
    {
        $this->validate([
            'goalComplete'  => 'boolean',
            'achievedAt'    => 'nullable|required_if:goalComplete,true|date',
            'outcomeTitle'  => 'required|string|max:500',
            'outcomeBody'   => 'required|string',
            'significance'  => 'required|integer|min:1|max:5',
        ]);

        $goal = Goal::findOrFail($this->editingId);
        abort_if($goal->user_id !== auth()->id(), 403);

        if ($this->goalComplete) {
            $goal->markAchieved($this->achievedAt ? new \DateTime($this->achievedAt) : null);
        }

        $outcome = Outcome::create([
            'user_id'      => auth()->id(),
            'goal_id'      => $goal->id,
            'title'        => $this->outcomeTitle,
            'body'         => $this->outcomeBody,
            'significance' => $this->significance,
            'date'         => now()->format('Y-m-d'),
            'logged_at'    => now(),
        ]);

        // Attach same persons as the goal
        $personIds = $goal->persons()->pluck('persons.id');
        $outcome->persons()->attach($personIds, ['is_primary' => true]);

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
        $need->resolve($this->resolutionDetails ?: null);
        $need->resolved_at = $this->resolvedAt;
        $need->save();

        $this->resetForm();
        $this->dispatch('notify', message: 'Prayer need resolved.');
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
        $this->dispatch('notify', message: 'Prayer need deleted.');
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
