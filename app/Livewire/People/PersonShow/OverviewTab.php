<?php

namespace App\Livewire\People\PersonShow;

use App\Models\KeyDate;
use App\Models\Person;
use App\Models\PersonName;
use App\Models\PersonPhoto;
use Livewire\Component;
use Livewire\WithFileUploads;

class OverviewTab extends Component
{
    use WithFileUploads;

    public Person $person;
    public bool $editing = false;

    // Person fields
    public ?string $gender = null;
    public ?string $date_of_death = null;
    public ?string $notes = null;

    // Birthday — stored as a KeyDate, not on Person
    public ?int $birthdayKeyDateId = null;
    public ?string $birthday = null;
    public bool $birthdayYearUnknown = false;

    // Primary name fields
    public ?int $primaryNameId = null;
    public string $firstName = '';
    public string $lastName = '';
    public string $middleNames = '';
    public string $preferredName = '';
    public string $nameType = 'birth';
    public bool $spellingUncertain = false;

    // Photo
    public $photoUpload = null;
    public bool $removePhoto = false;

    public function mount(Person $person): void
    {
        $this->person = $person->load(['names', 'addresses', 'photo']);
        $this->resetForm();
    }

    public function resetForm(): void
    {
        $this->gender      = $this->person->gender;
        $this->date_of_death = $this->person->date_of_death
            ? \Carbon\Carbon::parse($this->person->date_of_death)->format('Y-m-d')
            : null;
        $this->notes       = $this->person->notes;

        // Load birthday from KeyDate
        $birthdayKd = $this->person->keyDates()->where('type', 'birthday')->first();
        if ($birthdayKd) {
            $this->birthdayKeyDateId  = $birthdayKd->id;
            $this->birthday           = $birthdayKd->date->format('Y-m-d');
            $this->birthdayYearUnknown = $birthdayKd->year_unknown;
        } else {
            $this->birthdayKeyDateId  = null;
            $this->birthday           = null;
            $this->birthdayYearUnknown = false;
        }

        // Load primary name
        $primary = $this->person->names->firstWhere('is_primary', true)
            ?? $this->person->names->first();

        if ($primary) {
            $this->primaryNameId     = $primary->id;
            $this->firstName         = $primary->first_name ?? '';
            $this->lastName          = $primary->last_name ?? '';
            $this->middleNames       = $primary->middle_names ?? '';
            $this->preferredName     = $primary->preferred_name ?? '';
            $this->nameType          = $primary->type ?? 'birth';
            $this->spellingUncertain = $primary->spelling_uncertain ?? false;
        } else {
            $this->primaryNameId     = null;
            $this->firstName         = '';
            $this->lastName          = '';
            $this->middleNames       = '';
            $this->preferredName     = '';
            $this->nameType          = 'birth';
            $this->spellingUncertain = false;
        }

        $this->photoUpload = null;
        $this->removePhoto = false;
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
            'gender'               => 'nullable|in:male,female,unknown',
            'birthday'             => 'nullable|date',
            'date_of_death'        => 'nullable|date',
            'notes'                => 'nullable|string|max:10000',
            'firstName'            => 'nullable|string|max:255',
            'lastName'             => 'nullable|string|max:255',
            'middleNames'          => 'nullable|string|max:255',
            'preferredName'        => 'nullable|string|max:255',
            'nameType'             => 'required|in:birth,married,preferred,other',
            'photoUpload'          => 'nullable|image|max:10240',
        ]);

        // Save person fields (no date_of_birth here)
        $this->person->update([
            'gender'        => $this->gender,
            'date_of_death' => $this->date_of_death,
            'notes'         => $this->notes,
        ]);

        // Save birthday as KeyDate
        $this->saveBirthday();

        // Save primary name
        $nameData = [
            'first_name'       => $this->firstName ?: null,
            'last_name'        => $this->lastName ?: null,
            'middle_names'     => $this->middleNames ?: null,
            'preferred_name'   => $this->preferredName ?: null,
            'type'             => $this->nameType,
            'spelling_uncertain' => $this->spellingUncertain,
            'is_primary'       => true,
        ];

        if ($this->primaryNameId) {
            $name = PersonName::find($this->primaryNameId);
            if ($name && $name->person_id === $this->person->id) {
                $name->update($nameData);
            }
        } else {
            PersonName::create(array_merge($nameData, [
                'person_id' => $this->person->id,
            ]));
        }

        // Handle photo
        if ($this->removePhoto) {
            $this->person->photo?->delete();
        } elseif ($this->photoUpload) {
            $bytes    = file_get_contents($this->photoUpload->getRealPath());
            $mimeType = $this->photoUpload->getMimeType();
            $fileSize = strlen($bytes);
            $base64   = base64_encode($bytes);

            if ($this->person->photo) {
                $this->person->photo->update([
                    'data'      => $base64,
                    'mime_type' => $mimeType,
                    'file_size' => $fileSize,
                ]);
            } else {
                PersonPhoto::create([
                    'person_id' => $this->person->id,
                    'data'      => $base64,
                    'mime_type' => $mimeType,
                    'file_size' => $fileSize,
                ]);
            }
        }

        $this->person->refresh()->load(['names', 'addresses', 'photo']);
        $this->editing = false;
        $this->dispatch('notify', message: 'Profile updated.');
    }

    protected function saveBirthday(): void
    {
        if ($this->birthday) {
            $data = [
                'user_id'      => $this->person->user_id,
                'date'         => $this->birthday,
                'year_unknown' => $this->birthdayYearUnknown,
                'type'         => 'birthday',
                'is_recurring' => true,
                'significance' => 3,
                'logged_at'    => now(),
            ];

            if ($this->birthdayKeyDateId) {
                $kd = KeyDate::find($this->birthdayKeyDateId);
                if ($kd) {
                    $kd->update($data);
                }
            } else {
                $kd = KeyDate::create($data);
                $kd->persons()->attach($this->person->id, ['is_primary' => true]);
                $this->birthdayKeyDateId = $kd->id;
            }
        } else {
            // Birthday cleared — delete the KeyDate if one exists
            if ($this->birthdayKeyDateId) {
                KeyDate::find($this->birthdayKeyDateId)?->delete();
                $this->birthdayKeyDateId = null;
            }
        }
    }

    public function render()
    {
        return view('livewire.people.person-show.overview-tab');
    }
}
