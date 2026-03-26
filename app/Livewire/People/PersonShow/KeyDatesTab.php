<?php

namespace App\Livewire\People\PersonShow;

use App\Models\KeyDate;
use App\Models\Person;
use Livewire\Component;

class KeyDatesTab extends Component
{
    public Person $person;

    public bool $showForm = false;
    public ?int $editingId = null;

    public string $type = 'birthday';
    public string $date = '';
    public bool $yearUnknown = false;
    public ?string $label = null;
    public bool $isRecurring = true;
    public int $significance = 3;
    public bool $syncToCalendar = false;
    public ?string $googleCalendarId = null;

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
        $kd = KeyDate::findOrFail($id);
        abort_if($kd->user_id !== auth()->id(), 403);

        $this->editingId        = $kd->id;
        $this->type             = $kd->type;
        $this->date             = $kd->date->format('Y-m-d');
        $this->yearUnknown      = $kd->year_unknown;
        $this->label            = $kd->label;
        $this->isRecurring      = $kd->is_recurring;
        $this->significance     = $kd->significance;
        $this->googleCalendarId = $kd->google_calendar_id;
        $this->syncToCalendar   = (bool) $kd->google_calendar_event_id;
        $this->showForm         = true;
    }

    public function save(): void
    {
        $this->validate([
            'type'        => 'required|in:birthday,wedding_anniversary,bereavement,other',
            'date'        => 'required|date',
            'label'       => 'nullable|string|max:500',
            'significance' => 'required|integer|min:1|max:5',
        ]);

        $data = [
            'user_id'           => auth()->id(),
            'type'              => $this->type,
            'date'              => $this->date,
            'year_unknown'      => $this->yearUnknown,
            'label'             => $this->label,
            'is_recurring'      => $this->isRecurring,
            'significance'      => $this->significance,
            'logged_at'         => now(),
        ];

        if ($this->editingId) {
            $kd = KeyDate::findOrFail($this->editingId);
            abort_if($kd->user_id !== auth()->id(), 403);
            $kd->update($data);
        } else {
            $kd = KeyDate::create($data);
            // Attach to this person as primary
            $kd->persons()->attach($this->person->id, ['is_primary' => true]);
        }

        // Google Calendar sync handled via event/job (to be implemented)
        if ($this->syncToCalendar) {
            $this->dispatch('sync-key-date-to-calendar', keyDateId: $kd->id, calendarId: $this->googleCalendarId);
        }

        $this->resetForm();
        $this->dispatch('notify', message: 'Key date saved.');
    }

    public function delete(int $id): void
    {
        $kd = KeyDate::findOrFail($id);
        abort_if($kd->user_id !== auth()->id(), 403);
        $kd->delete();
        $this->dispatch('notify', message: 'Key date deleted.');
    }

    public function syncNow(int $id): void
    {
        $kd = KeyDate::findOrFail($id);
        abort_if($kd->user_id !== auth()->id(), 403);
        $this->dispatch('sync-key-date-to-calendar', keyDateId: $kd->id, calendarId: $kd->google_calendar_id);
        $this->dispatch('notify', message: 'Sync initiated.');
    }

    public function resetForm(): void
    {
        $this->showForm         = false;
        $this->editingId        = null;
        $this->type             = 'birthday';
        $this->date             = '';
        $this->yearUnknown      = false;
        $this->label            = null;
        $this->isRecurring      = true;
        $this->significance     = 3;
        $this->syncToCalendar   = false;
        $this->googleCalendarId = null;
    }

    public function render()
    {
        $keyDates = $this->person->keyDates()
            ->get()
            ->sortBy('days_until')
            ->values();

        return view('livewire.people.person-show.key-dates-tab', [
            'keyDates' => $keyDates,
        ]);
    }
}
