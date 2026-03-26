<?php

namespace App\Livewire\People;

use App\Models\Person;
use App\Services\Google\GoogleCalendarService;
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

    /**
     * Called asynchronously after page load via wire:init.
     * Checks synced key dates against Google Calendar and applies any remote changes.
     */
    public function checkCalendarSync(): void
    {
        try {
            $service = new GoogleCalendarService(auth()->user());
            $updated = $service->checkForRemoteChanges(auth()->user());

            if ($updated->isNotEmpty()) {
                $count = $updated->count();
                if ($count === 1) {
                    $kd      = $updated->first();
                    $label   = $kd->label ?? ucfirst(str_replace('_', ' ', $kd->type));
                    $message = "1 key date updated from Google Calendar: {$label}";
                } else {
                    $message = "{$count} key dates updated from Google Calendar.";
                }
                $this->dispatch('notify', message: $message);
            }
        } catch (\Exception $e) {
            // Silently fail — sync check is non-critical
        }
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
