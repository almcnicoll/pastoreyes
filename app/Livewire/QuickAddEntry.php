<?php

namespace App\Livewire;

use App\Models\Goal;
use App\Models\KeyDate;
use App\Models\Note;
use App\Models\PrayerNeed;
use Livewire\Component;

class QuickAddEntry extends Component
{
    public bool $open = false;
    public string $type = '';
    public ?int $personId = null;

    // Common fields
    public string $title = '';
    public string $body = '';
    public int $significance = 3;
    public string $date = '';

    // Goal-specific
    public ?string $targetDate = null;

    protected $listeners = [
        'open-quick-add'  => 'openModal',
        'person-selected' => 'onPersonSelected',
    ];

    public function onPersonSelected(int $personId): void
    {
        $this->personId = $personId;
    }

    public function mount(): void
    {
        $this->date = now()->format('Y-m-d');
    }

    public function openModal(?int $personId = null): void
    {
        $this->personId = $personId;
        $this->open     = true;
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
            'personId'    => 'required|integer|exists:persons,id',
            'type'        => 'required|in:note,prayer_need,goal,key_date',
            'body'        => 'required|string',
            'significance' => 'required|integer|min:1|max:5',
            'date'        => 'required|date',
        ]);

        $entry = match($this->type) {
            'note'       => $this->saveNote(),
            'prayer_need' => $this->savePrayerNeed(),
            'goal'       => $this->saveGoal(),
            default      => null,
        };

        if ($entry) {
            $entry->persons()->attach($this->personId, ['is_primary' => true]);
        }

        $this->reset(['open', 'type', 'personId', 'title', 'body', 'significance', 'targetDate']);
        $this->date = now()->format('Y-m-d');
        $this->significance = 3;

        $this->dispatch('notify', message: 'Entry added.');
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
        $this->reset(['open', 'type', 'personId', 'title', 'body', 'targetDate']);
        $this->date        = now()->format('Y-m-d');
        $this->significance = 3;
    }

    public function render()
    {
        return view('livewire.quick-add-entry');
    }
}
