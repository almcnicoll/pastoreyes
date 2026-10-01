<?php

namespace App\Livewire;

use App\Services\PrayerEmailScheduler;
use Livewire\Component;

class PrayerEmailSettings extends Component
{
    public bool $enabled = false;
    public string $timezone = 'Europe/London';
    public array $days = [];
    public bool $sameTime = true;
    public string $time = '09:00';
    public array $times = []; // ISO weekday => 'HH:MM'

    public function mount(): void
    {
        $config = auth()->user()->settings['prayer_email'] ?? [];

        $this->enabled  = (bool) ($config['enabled'] ?? false);
        $this->timezone = $config['timezone'] ?? 'Europe/London';
        $this->days     = array_map('intval', $config['days'] ?? []);
        $this->sameTime = (bool) ($config['same_time'] ?? true);
        $this->time     = $config['time'] ?? '09:00';
        $this->times    = $config['times'] ?? [];
    }

    // Newly ticked days start at the common time, so their per-day box isn't blank
    public function updatedDays(): void
    {
        foreach ($this->days as $day) {
            $this->times[(int) $day] ??= $this->time;
        }
    }

    public function save(PrayerEmailScheduler $scheduler): void
    {
        $this->days = array_values(array_unique(array_map('intval', $this->days)));
        sort($this->days);

        $this->validate([
            'timezone' => 'required|timezone',
            'days'     => $this->enabled ? 'required|array|min:1' : 'array',
            'days.*'   => 'integer|between:1,7',
            'time'     => 'required|date_format:H:i',
            'times.*'  => 'nullable|date_format:H:i',
        ], [
            'days.required' => 'Choose at least one day.',
        ]);

        // Keep per-day times only for selected days, defaulting to the common time
        $times = [];
        foreach ($this->days as $day) {
            $times[$day] = $this->times[$day] ?? $this->time;
        }

        $user     = auth()->user();
        $settings = $user->settings ?? [];

        $settings['prayer_email'] = [
            'enabled'      => $this->enabled,
            'timezone'     => $this->timezone,
            'days'         => $this->days,
            'same_time'    => $this->sameTime,
            'time'         => $this->time,
            'times'        => $times,
            'last_sent_on' => $settings['prayer_email']['last_sent_on'] ?? null,
        ];
        $user->update(['settings' => $settings]);

        // Spread people over the newly selected days
        $scheduler->rebalance($user->fresh());

        $this->dispatch('notify', message: 'Prayer email settings saved.');
    }

    public function render()
    {
        return view('livewire.prayer-email-settings', [
            'weekdays'  => PrayerEmailScheduler::DAYS,
            'timezones' => \DateTimeZone::listIdentifiers(),
        ]);
    }
}
