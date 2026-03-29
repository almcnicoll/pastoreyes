<?php

namespace App\Livewire\People\PersonShow;

use App\Models\Person;
use App\Models\PersonName;
use Livewire\Component;

class ManagePersonNames extends Component
{
    public Person $person;
    public bool $showForm = false;
    public ?int $editingId = null;

    public string $firstName = '';
    public string $lastName = '';
    public string $middleNames = '';
    public string $preferredName = '';
    public string $type = 'birth';
    public bool $spellingUncertain = false;
    public string $dateFrom = '';
    public string $dateTo = '';
    public bool $isPrimary = false;
    public string $notes = '';

    public function mount(Person $person): void
    {
        $this->person = $person;
    }

    public function openAdd(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $name = PersonName::findOrFail($id);
        abort_if($name->person_id !== $this->person->id, 403);

        $this->editingId        = $id;
        $this->firstName        = $name->first_name ?? '';
        $this->lastName         = $name->last_name ?? '';
        $this->middleNames      = $name->middle_names ?? '';
        $this->preferredName    = $name->preferred_name ?? '';
        $this->type             = $name->type;
        $this->spellingUncertain = $name->spelling_uncertain;
        $this->dateFrom         = $name->date_from ?? '';
        $this->dateTo           = $name->date_to ?? '';
        $this->isPrimary        = $name->is_primary;
        $this->notes            = $name->notes ?? '';
        $this->showForm         = true;
    }

    public function save(): void
    {
        $this->validate([
            'firstName'   => 'nullable|string|max:255',
            'lastName'    => 'nullable|string|max:255',
            'middleNames' => 'nullable|string|max:255',
            'preferredName' => 'nullable|string|max:255',
            'type'        => 'required|in:birth,married,preferred,other',
            'dateFrom'    => 'nullable|date',
            'dateTo'      => 'nullable|date',
            'notes'       => 'nullable|string|max:500',
        ]);

        if (empty($this->firstName) && empty($this->lastName)) {
            $this->addError('firstName', 'Please enter at least a first or last name.');
            return;
        }

        $data = [
            'first_name'       => $this->firstName ?: null,
            'last_name'        => $this->lastName ?: null,
            'middle_names'     => $this->middleNames ?: null,
            'preferred_name'   => $this->preferredName ?: null,
            'type'             => $this->type,
            'spelling_uncertain' => $this->spellingUncertain,
            'date_from'        => $this->dateFrom ?: null,
            'date_to'          => $this->dateTo ?: null,
            'notes'            => $this->notes ?: null,
            'is_primary'       => $this->isPrimary,
        ];

        if ($this->editingId) {
            $name = PersonName::findOrFail($this->editingId);
            abort_if($name->person_id !== $this->person->id, 403);
            $name->update($data);

            if ($this->isPrimary) {
                $name->setAsPrimary();
            }
        } else {
            $name = PersonName::create(array_merge($data, [
                'person_id' => $this->person->id,
            ]));

            if ($this->isPrimary) {
                $name->setAsPrimary();
            }
        }

        $this->resetForm();
        $this->dispatch('notify', message: 'Name saved.');
        $this->dispatch('person-names-updated');
    }

    public function delete(int $id): void
    {
        $name = PersonName::findOrFail($id);
        abort_if($name->person_id !== $this->person->id, 403);

        if ($name->is_primary && $this->person->names()->count() > 1) {
            $this->dispatch('notify', message: 'Cannot delete the primary name while other names exist. Set another name as primary first.');
            return;
        }

        $name->delete();
        $this->dispatch('notify', message: 'Name deleted.');
    }

    public function resetForm(): void
    {
        $this->showForm         = false;
        $this->editingId        = null;
        $this->firstName        = '';
        $this->lastName         = '';
        $this->middleNames      = '';
        $this->preferredName    = '';
        $this->type             = 'birth';
        $this->spellingUncertain = false;
        $this->dateFrom         = '';
        $this->dateTo           = '';
        $this->isPrimary        = false;
        $this->notes            = '';
    }

    public function render()
    {
        $names = $this->person->names()->orderByDesc('is_primary')->get();

        return view('livewire.people.person-show.manage-person-names', compact('names'));
    }
}
