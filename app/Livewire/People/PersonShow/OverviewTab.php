<?php

namespace App\Livewire\People\PersonShow;

use App\Models\Person;
use App\Models\PersonName;
use Livewire\Component;

class OverviewTab extends Component
{
    public Person $person;
    public bool $editing = false;

    // Editable fields
    public ?string $gender = null;
    public ?string $date_of_birth = null;
    public bool $dob_year_unknown = false;
    public ?string $date_of_death = null;
    public ?string $notes = null;

    public function mount(Person $person): void
    {
        $this->person = $person->load(['names', 'addresses', 'photo']);
        $this->resetForm();
    }

    public function resetForm(): void
    {
        $this->gender         = $this->person->gender;
        $this->date_of_birth  = $this->person->date_of_birth
            ? \Carbon\Carbon::parse($this->person->date_of_birth)->format('Y-m-d')
            : null;
        $this->dob_year_unknown = $this->person->dob_year_unknown;
        $this->date_of_death  = $this->person->date_of_death
            ? \Carbon\Carbon::parse($this->person->date_of_death)->format('Y-m-d')
            : null;
        $this->notes          = $this->person->notes;
    }

    public function startEditing(): void
    {
        $this->editing = true;
    }

    public function cancelEditing(): void
    {
        $this->editing = false;
        $this->resetForm();
    }

    public function save(): void
    {
        $this->validate([
            'gender'         => 'nullable|in:male,female,unknown',
            'date_of_birth'  => 'nullable|date',
            'date_of_death'  => 'nullable|date',
            'notes'          => 'nullable|string|max:10000',
        ]);

        $this->person->update([
            'gender'          => $this->gender,
            'date_of_birth'   => $this->date_of_birth,
            'dob_year_unknown' => $this->dob_year_unknown,
            'date_of_death'   => $this->date_of_death,
            'notes'           => $this->notes,
        ]);

        $this->person->refresh();
        $this->editing = false;
        $this->dispatch('notify', message: 'Profile updated.');
    }

    public function render()
    {
        return view('livewire.people.person-show.overview-tab');
    }
}