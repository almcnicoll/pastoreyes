<?php

namespace App\Livewire;

use App\Livewire\Concerns\CatchesDbErrors;
use App\Models\Goal;
use App\Models\KeyDate;
use App\Models\Note;
use App\Models\Person;
use App\Models\PrayerNeed;
use Livewire\Component;

class QuickAddEntry extends Component
{
    use CatchesDbErrors;
    public bool $open = false;
    public string $type = '';
    public array $selectedPersonIds = [];
    public string $personSearch = '';
    public $personResults = null;

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

    public function updatedPersonSearch(): void
    {
        if (strlen($this->personSearch) < 1) {
            $this->personResults = collect();
            return;
        }

        $search          = strtolower($this->personSearch);
        $alreadySelected = $this->selectedPersonIds;

        $this->personResults = Person::where('user_id', auth()->id())
            ->with('primaryName')
            ->get()
            ->filter(fn($p) =>
                str_contains(strtolower($p->display_name), $search) &&
                !in_array($p->id, $alreadySelected)
            )
            ->take(8)
            ->values();
    }

    public function addPerson(int $personId): void
    {
        if (!in_array($personId, $this->selectedPersonIds)) {
            $this->selectedPersonIds[] = $personId;
        }
        $this->personSearch  = '';
        $this->personResults = collect();
    }

    public function removePerson(int $personId): void
    {
        $this->selectedPersonIds = array_values(
            array_filter($this->selectedPersonIds, fn($id) => $id !== $personId)
        );
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
            // First person selected is the primary one (same convention as Timeline::saveEdit)
            foreach ($this->selectedPersonIds as $i => $personId) {
                $entry->persons()->attach($personId, ['is_primary' => $i === 0]);
            }
        }

        $this->reset(['open', 'type', 'selectedPersonIds', 'personSearch', 'personResults', 'title', 'body', 'significance', 'targetDate']);
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
        $this->reset(['open', 'type', 'selectedPersonIds', 'personSearch', 'personResults', 'title', 'body', 'targetDate']);
        $this->date        = now()->format('Y-m-d');
        $this->significance = 3;
    }

    public function render()
    {
        // Keep chips in the order people were selected
        $selectedPersons = Person::whereIn('id', $this->selectedPersonIds)->with('primaryName')->get()
            ->sortBy(fn($p) => array_search($p->id, $this->selectedPersonIds))
            ->values();

        return view('livewire.quick-add-entry', ['selectedPersons' => $selectedPersons]);
    }
}
