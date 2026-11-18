<?php

namespace App\Livewire;

use App\Models\Person;
use App\Services\PersonSearch;
use Livewire\Attributes\Modelable;
use Livewire\Component;

/**
 * Find-a-person picker. Bind it with wire:model:
 *   mode="single": the parent property is ?int (the chosen person's id)
 *   mode="multi":  the parent property is int[] (chips, first one is primary by convention)
 * Also dispatches `person-selected` / `person-cleared` ({ personId }) for listeners.
 */
class PersonPicker extends Component
{
    #[Modelable]
    public mixed $value = null;

    public string $mode = 'single';
    public ?int $excludeId = null;
    public string $placeholder = 'Search for a person...';

    public string $search = '';

    public function mount(): void
    {
        $this->value = $this->normalise($this->value);
    }

    protected function multi(): bool
    {
        return $this->mode === 'multi';
    }

    protected function normalise(mixed $value): mixed
    {
        return $this->multi() ? array_values(array_map('intval', (array) $value)) : ($value ? (int) $value : null);
    }

    protected function selectedIds(): array
    {
        return $this->multi() ? (array) $this->value : array_filter([$this->value]);
    }

    public function select(int $personId): void
    {
        // Only ever pick one of the user's own people
        if (!Person::where('user_id', auth()->id())->whereKey($personId)->exists()) {
            return;
        }

        if ($this->multi()) {
            $this->value = array_values(array_unique([...(array) $this->value, $personId]));
        } else {
            $this->value = $personId;
        }

        $this->search = '';
        $this->dispatch('person-selected', personId: $personId);
    }

    public function remove(int $personId): void
    {
        $this->value = $this->multi()
            ? array_values(array_filter((array) $this->value, fn($id) => $id !== $personId))
            : null;

        $this->dispatch('person-cleared', personId: $personId);
    }

    public function render()
    {
        $ids = $this->selectedIds();

        // Chips keep the order people were selected
        $selected = $ids
            ? Person::where('user_id', auth()->id())->whereIn('id', $ids)->with('primaryName')->get()
                ->sortBy(fn($p) => array_search($p->id, $ids))->values()
            : collect();

        $exclude = array_filter([...$ids, $this->excludeId]);

        return view('livewire.person-picker', [
            'selected' => $selected,
            'results'  => PersonSearch::find(auth()->id(), $this->search, $exclude),
        ]);
    }
}
