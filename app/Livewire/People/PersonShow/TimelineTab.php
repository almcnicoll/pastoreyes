<?php

namespace App\Livewire\People\PersonShow;

use App\Models\Person;
use Livewire\Component;

class TimelineTab extends Component
{
    public Person $person;

    public function mount(Person $person): void
    {
        $this->person = $person;
    }

    public function render()
    {
        return view('livewire.people.person-show.timeline-tab');
    }
}
