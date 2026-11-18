<?php

namespace App\Livewire;

use App\Livewire\Concerns\CatchesDbErrors;
use App\Models\Goal;
use App\Models\Note;
use App\Models\PrayerNeed;
use Livewire\Component;

class QuickAddEntry extends Component
{
    use CatchesDbErrors;
    public bool $open = false;
    public string $type = '';
    public array $selectedPersonIds = [];

    // Common fields
    public string $title = '';
    public string $body = '';
    public int $significance = 3;
    public string $date = '';

    // Goal-specific
    public ?string $targetDate = null;

    protected $listeners = [
        'open-quick-add' => 'openModal',
    ];

    public function mount(?int $personId = null): void
    {
        $this->date = now()->format('Y-m-d');
        $this->selectedPersonIds = $personId ? [$personId] : [];
    }

    public function openModal(?int $personId = null): void
    {
        $this->selectedPersonIds = $personId ? [$personId] : [];
        $this->open              = true;
    }

    public function updatedType(): void
    {
        // Reset type-specific fields when type changes
        $this->title      = '';
        $this->body       = '';
        $this->targetDate = null;
    }

    public function save(): void
    {
        $this->validate([
            'selectedPersonIds'   => 'required|array|min:1',
            'selectedPersonIds.*' => 'integer|exists:persons,id',
            'type'        => 'required|in:note,prayer_need,goal',
            // A goal needs a title, as on the person's Goals & Prayer tab; for the others it's optional
            'title'       => ($this->type === 'goal' ? 'required' : 'nullable') . '|string|max:500',
            'body'        => 'required|string',
            'significance' => 'required|integer|min:1|max:5',
            'date'        => 'required|date',
            'targetDate'  => 'nullable|date',
        ]);

        $entry = match($this->type) {
            'note'        => $this->saveNote(),
            'prayer_need' => $this->savePrayerNeed(),
            'goal'        => $this->saveGoal(),
        };

        // First person selected is the primary one (same convention as Timeline::saveEdit)
        foreach ($this->selectedPersonIds as $i => $personId) {
            $entry->persons()->attach($personId, ['is_primary' => $i === 0]);
        }

        $label = config("entry_types.types.{$this->type}.label");

        $this->reset(['open', 'type', 'selectedPersonIds', 'title', 'body', 'significance', 'targetDate']);
        $this->date = now()->format('Y-m-d');
        $this->significance = 3;

        $this->dispatch('notify', message: "{$label} added.");
        $this->dispatch('timeline-updated');
    }

    protected function saveNote(): Note
    {
        return Note::create([
            'user_id'     => auth()->id(),
            'title'       => $this->title ?: null,
            'body'        => $this->body,
            'significance' => $this->significance,
            'date'        => $this->date,
            'logged_at'   => now(),
        ]);
    }

    protected function savePrayerNeed(): PrayerNeed
    {
        return PrayerNeed::create([
            'user_id'     => auth()->id(),
            'title'       => $this->title ?: null,
            'body'        => $this->body,
            'significance' => $this->significance,
            'date'        => $this->date,
            'logged_at'   => now(),
        ]);
    }

    protected function saveGoal(): Goal
    {
        return Goal::create([
            'user_id'     => auth()->id(),
            'title'       => $this->title,
            'body'        => $this->body,
            'significance' => $this->significance,
            'date'        => $this->date,
            'target_date' => $this->targetDate,
            'logged_at'   => now(),
        ]);
    }

    public function closeModal(): void
    {
        $this->reset(['open', 'type', 'selectedPersonIds', 'title', 'body', 'targetDate']);
        $this->date        = now()->format('Y-m-d');
        $this->significance = 3;
    }

    public function render()
    {
        return view('livewire.quick-add-entry');
    }
}
