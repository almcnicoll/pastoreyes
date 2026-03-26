<?php

namespace App\Livewire;

use App\Services\Google\GoogleCalendarService;
use Livewire\Component;

class GoogleIntegrationSettings extends Component
{
    public bool $loadingCalendars = false;
    public bool $calendarsError = false;
    public array $calendars = [];
    public ?string $defaultCalendarId = null;

    public function mount(): void
    {
        $this->defaultCalendarId = auth()->user()->settings['default_calendar_id'] ?? null;
    }

    public function loadCalendars(): void
    {
        $this->loadingCalendars = true;
        $this->calendarsError   = false;

        try {
            $service         = new GoogleCalendarService(auth()->user());
            $this->calendars = $service->getEditableCalendars()->toArray();

            if (!$this->defaultCalendarId && count($this->calendars)) {
                $primary = collect($this->calendars)->firstWhere('primary', true);
                if ($primary) {
                    $this->defaultCalendarId = $primary['id'];
                }
            }
        } catch (\Exception $e) {
            $this->calendarsError = true;
        }

        $this->loadingCalendars = false;
    }

    public function saveDefaultCalendar(): void
    {
        $settings = auth()->user()->settings ?? [];
        $settings['default_calendar_id'] = $this->defaultCalendarId;
        auth()->user()->update(['settings' => $settings]);
        $this->dispatch('notify', message: 'Default calendar saved.');
    }

    public function disconnectGoogle(): void
    {
        auth()->user()->update([
            'google_oauth_token'         => null,
            'google_oauth_refresh_token' => null,
            'google_token_expires_at'    => null,
        ]);
        $this->dispatch('notify', message: 'Google account disconnected.');
    }

    public function render()
    {
        return view('livewire.google-integration-settings');
    }
}
