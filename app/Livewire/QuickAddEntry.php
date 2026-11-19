<?php

namespace App\Livewire;

use App\Actions\CreateEntry;
use App\Actions\EntryRules;
use App\Livewire\Concerns\CatchesDbErrors;
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
            'type'                => 'required|in:note,prayer_need,goal',
        ] + EntryRules::livewire(in_array($this->type, ['prayer_need', 'goal'], true) ? $this->type : 'note'));

        // The first person selected is the primary one (same convention as Timeline::saveEdit)
        (new CreateEntry)->execute(auth()->id(), $this->type, $this->selectedPersonIds, [
            'title'        => $this->title ?: null,
            'body'         => $this->body,
            'significance' => $this->significance,
            'date'         => $this->date,
        ] + ($this->type === 'goal' ? ['target_date' => $this->targetDate] : []));

        $label = config("entry_types.types.{$this->type}.label");

        $this->reset(['open', 'type', 'selectedPersonIds', 'title', 'body', 'significance', 'targetDate']);
        $this->date = now()->format('Y-m-d');
        $this->significance = 3;

        $this->dispatch('notify', message: "{$label} added.");
        $this->dispatch('timeline-updated');
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
