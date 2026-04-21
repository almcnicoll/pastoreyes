<?php

namespace App\Livewire\People;

use App\Livewire\Concerns\CatchesDbErrors;
use App\Models\Person;
use App\Models\PersonName;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithFileUploads;

class AddPerson extends Component
{
    use CatchesDbErrors;
    public bool $open = false;

    // Name fields
    public string $firstName = '';
    public string $lastName = '';
    public string $preferredName = '';
    public string $nameType = 'birth';
    public bool $spellingUncertain = false;

    // Person fields
    public string $gender = '';

    protected $listeners = [
        'open-add-person' => 'openModal',
    ];

    public function openModal(): void
    {
        $this->reset([
            'firstName', 'lastName', 'preferredName', 'nameType',
            'spellingUncertain', 'gender',
        ]);
        $this->nameType = 'birth';
        $this->open     = true;
    }

    public function save(): void
    {
        $this->validate([
            'firstName'   => 'nullable|string|max:255',
            'lastName'    => 'nullable|string|max:255',
            'nameType'    => 'required|in:birth,married,preferred,other',
            'gender'      => 'nullable|in:male,female,unknown',
        ]);

        // Require at least one name field
        if (empty($this->firstName) && empty($this->lastName)) {
            $this->addError('firstName', 'Please enter at least a first or last name.');
            return;
        }

        DB::transaction(function () {
            $person = Person::create([
                'user_id'         => auth()->id(),
                'gender'          => $this->gender ?: null,
            ]);

            PersonName::create([
                'person_id'        => $person->id,
                'first_name'       => $this->firstName ?: null,
                'last_name'        => $this->lastName ?: null,
                'preferred_name'   => $this->preferredName ?: null,
                'type'             => $this->nameType,
                'spelling_uncertain' => $this->spellingUncertain,
                'is_primary'       => true,
            ]);

            $this->open = false;
            $this->dispatch('notify', message: 'Person added.');
            $this->redirect(route('people.show', $person), navigate: true);
        });
    }

    public function closeModal(): void
    {
        $this->open = false;
    }

    public function render()
    {
        return view('livewire.people.add-person');
    }
}