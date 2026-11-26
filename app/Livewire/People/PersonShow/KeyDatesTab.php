<?php

namespace App\Livewire\People\PersonShow;

use App\Actions\CreateEntry;
use App\Actions\EntryRules;
use App\Actions\SyncKeyDateToCalendar;
use App\Livewire\Concerns\CatchesDbErrors;
use App\Models\KeyDate;
use App\Models\Person;
use Livewire\Component;

class KeyDatesTab extends Component
{
    use CatchesDbErrors;
    public Person $person;

    public bool $showForm = false;
    public ?int $editingId = null;

    public string $type = 'birthday';
    public string $date = '';
    public bool $yearUnknown = false;
    public ?string $label = null;
    public bool $isRecurring = true;
    public ?int $recurrenceCount = null;
    public int $significance = 3;
    public bool $syncToCalendar = false;
    public ?string $googleCalendarId = null;

    public function mount(Person $person): void
    {
        $this->person = $person;
    }

    public function syncKeyDate(int $keyDateId, ?string $calendarId = null): void
    {
        $kd = KeyDate::findOrFail($keyDateId);
        abort_if($kd->user_id !== auth()->id(), 403);

        $success = (new SyncKeyDateToCalendar())->execute($kd, $calendarId);

        $this->dispatch('notify', message: $success
            ? 'Synced to Google Calendar.'
            : 'Sync failed — check your Google connection in Settings.'
        );
    }

    /** A Death is a memorial that recurs for its first three anniversaries, then stops. */
    public function updatedType(string $value): void
    {
        if ($value === 'death' && !$this->editingId) {
            $this->isRecurring     = true;
            $this->recurrenceCount = 3;
        }
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
        $this->recurrenceCount  = $kd->recurrence_count;
        $this->significance     = $kd->significance;
        $this->googleCalendarId = $kd->google_calendar_id;
        $this->syncToCalendar   = (bool) $kd->google_calendar_event_id;
        $this->showForm         = true;
    }

    public function save(): void
    {
        $this->validate(EntryRules::livewire('key_date'));

        $data = [
            'type'              => $this->type,
            'date'              => $this->date,
            'year_unknown'      => $this->yearUnknown,
            'label'             => $this->label,
            'is_recurring'      => $this->isRecurring,
            'recurrence_count'  => $this->isRecurring ? $this->recurrenceCount : null,
            'significance'      => $this->significance,
        ];

        if ($this->editingId) {
            $kd = KeyDate::findOrFail($this->editingId);
            abort_if($kd->user_id !== auth()->id(), 403);
            $kd->update($data + ['logged_at' => now()]);
        } else {
            // Attached to this person as primary
            $kd = (new CreateEntry)->execute(auth()->id(), 'key_date', [$this->person->id], $data);
        }

        $verb = $this->editingId ? 'updated' : 'added';
        $sync = $this->syncToCalendar ? [$kd->id, $this->googleCalendarId] : null;
        $this->resetForm();
        $this->dispatch('notify', message: "Key Date {$verb}.");

        // Last, so its own toast ("Synced…" or the failure) is the one left showing
        if ($sync) {
            $this->syncKeyDate(...$sync);
        }
    }

    public function delete(int $id): void
    {
        $kd = KeyDate::findOrFail($id);
        abort_if($kd->user_id !== auth()->id(), 403);
        $kd->delete();
        $this->dispatch('notify', message: 'Key Date deleted.');
    }

    public function syncNow(int $id): void
    {
        $this->syncKeyDate($id); // ponytail: was dispatching an event nothing listened for
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
        $this->recurrenceCount  = null;
        $this->significance     = 3;
        $this->syncToCalendar   = false;
        $this->googleCalendarId = null;
    }

    public function render()
    {
        $keyDates = $this->person->keyDates()
            ->get()
            ->sortBy(fn($kd) => $kd->days_until ?? PHP_INT_MAX)
            ->values();

        return view('livewire.people.person-show.key-dates-tab', [
            'keyDates' => $keyDates,
        ]);
    }
}
