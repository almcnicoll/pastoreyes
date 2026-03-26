<?php

namespace App\Livewire\People;

use App\Models\Person;
use Illuminate\Support\Collection;
use Livewire\Component;
use Livewire\WithPagination;

class PeopleIndex extends Component
{
    use WithPagination;

    public string $search = '';
    public string $sortBy = 'name';
    public string $sortDirection = 'asc';

    protected $queryString = [
        'search'        => ['except' => ''],
        'sortBy'        => ['except' => 'name'],
        'sortDirection' => ['except' => 'asc'],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function sort(string $field): void
    {
        if ($this->sortBy === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $field;
            $this->sortDirection = 'asc';
        }
        $this->resetPage();
    }

    public function render()
    {
        $userId = auth()->id();

        // Load all persons with their primary name and photo
        // Searching happens in PHP after decryption since name fields are encrypted
        $persons = Person::where('user_id', $userId)
            ->with(['primaryName', 'photo'])
            ->get()
            ->filter(function ($person) {
                if (empty($this->search)) {
                    return true;
                }
                $search = strtolower($this->search);
                $name = strtolower($person->display_name);
                return str_contains($name, $search);
            })
            ->sortBy(function ($person) {
                return match($this->sortBy) {
                    'name' => strtolower($person->display_name),
                    default => strtolower($person->display_name),
                };
            }, SORT_REGULAR, $this->sortDirection === 'desc')
            ->values();

        return view('livewire.people.index', [
            'persons' => $persons,
        ])->layout('layouts.app', ['title' => 'People — PastorEyes']);
    }
}
