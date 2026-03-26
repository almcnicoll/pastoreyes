<?php

namespace App\Livewire\People;

use App\Models\Person;
use Livewire\Component;

class PersonShow extends Component
{
    public Person $person;
    public string $activeTab = 'overview';

    public function mount(Person $person): void
    {
        // Ensure this person belongs to the authenticated user
        abort_if($person->user_id !== auth()->id(), 403);

        $this->person = $person;
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function render()
    {
        return view('livewire.people.show', [
            'person' => $this->person->load('primaryName'),
        ])->layout('layouts.app', [
            'title' => $this->person->display_name . ' — PastorEyes',
        ]);
    }
}
