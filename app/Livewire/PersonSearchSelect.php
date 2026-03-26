<?php

namespace App\Livewire;

use App\Models\Person;
use Illuminate\Support\Collection;
use Livewire\Component;

class PersonSearchSelect extends Component
{
    public ?int $value = null;
    public string $search = '';
    public bool $open = false;
    public ?int $excludeId = null;

    public Collection $results;

    public function mount(?int $excludeId = null, ?int $value = null): void
    {
        $this->excludeId = $excludeId;
        $this->value     = $value;
        $this->results   = collect();
    }

    public function updatedSearch(): void
    {
        if (strlen($this->search) < 1) {
            $this->results = collect();
            return;
        }

        $search = strtolower($this->search);

        $this->results = Person::where('user_id', auth()->id())
            ->with('primaryName')
            ->when($this->excludeId, fn($q) => $q->where('id', '!=', $this->excludeId))
            ->get()
            ->filter(fn($p) => str_contains(strtolower($p->display_name), $search))
            ->take(8)
            ->values();

        $this->open = $this->results->isNotEmpty();
    }

    public function select(int $personId, string $name): void
    {
        $this->value  = $personId;
        $this->search = $name;
        $this->open   = false;
        $this->results = collect();
        $this->dispatch('person-selected', personId: $personId);
    }

    public function clear(): void
    {
        $this->value  = null;
        $this->search = '';
        $this->open   = false;
        $this->results = collect();
    }

    public function render()
    {
        // If a value is set but search is empty, populate search with the person's name
        if ($this->value && empty($this->search)) {
            $person = Person::with('primaryName')->find($this->value);
            if ($person) {
                $this->search = $person->display_name;
            }
        }

        return view('livewire.person-search-select');
    }
}
