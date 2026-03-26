<?php

namespace App\Livewire;

use App\Services\Google\GoogleCalendarService;
use Illuminate\Support\Collection;
use Livewire\Component;

class GoogleCalendarSelector extends Component
{
    public ?string $value = null;
    public Collection $calendars;
    public bool $loading = true;
    public bool $error = false;

    public function mount(?string $value = null): void
    {
        $this->value     = $value;
        $this->calendars = collect();
    }

    public function loadCalendars(): void
    {
        $this->loading = true;
        $this->error   = false;

        try {
            $service         = new GoogleCalendarService(auth()->user());
            $this->calendars = $service->getEditableCalendars();

            // Auto-select primary calendar if no value set
            if (!$this->value) {
                $primary = $this->calendars->firstWhere('primary', true);
                if ($primary) {
                    $this->value = $primary['id'];
                }
            }
        } catch (\Exception $e) {
            $this->error = true;
        }

        $this->loading = false;
    }

    public function render()
    {
        return view('livewire.google-calendar-selector');
    }
}
