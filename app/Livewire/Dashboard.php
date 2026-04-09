<?php

namespace App\Livewire;

use App\Models\Goal;
use App\Models\KeyDate;
use App\Models\PrayerNeed;
use App\Models\Task;
use App\Models\TimelineEntry;
use Illuminate\Support\Collection;
use Livewire\Component;

class Dashboard extends Component
{
    public int $upcomingDaysWindow = 30;

    public function mount(): void
    {
        // Load the user's preferred upcoming window from settings if stored
        $this->upcomingDaysWindow = auth()->user()->settings['upcoming_days_window'] ?? 30;
    }

    /**
     * Upcoming key dates within the configured window, sorted by next occurrence.
     */
    public function getUpcomingKeyDatesProperty(): Collection
    {
        return KeyDate::where('user_id', auth()->id())
            ->with(['persons.primaryName'])
            ->get()
            ->filter(
                fn($kd) => $kd->days_until !== null && 
                $kd->days_until >= -1 &&
                $kd->days_until <= $this->upcomingDaysWindow
            )
            ->sortBy('days_until')
            ->values();
    }

    /**
     * Unresolved prayer needs, sorted by significance desc then date desc.
     */
    public function getUnresolvedPrayerNeedsProperty(): Collection
    {
        return PrayerNeed::where('user_id', auth()->id())
            ->unresolved()
            ->with(['persons.primaryName'])
            ->orderByDesc('significance')
            ->orderByDesc('date')
            ->limit(5)
            ->get();
    }

    /**
     * Goals with a target date approaching within the window.
     */
    public function getApproachingGoalsProperty(): Collection
    {
        return Goal::where('user_id', auth()->id())
            ->approaching($this->upcomingDaysWindow)
            ->with(['persons.primaryName'])
            ->orderBy('target_date')
            ->limit(5)
            ->get();
    }

    /**
     * Most recently logged timeline entries across all people.
     */
    public function getRecentActivityProperty(): Collection
    {
        return TimelineEntry::forUser(auth()->id())
            ->orderByDesc('logged_at')
            ->limit(5)
            ->get()
            ->map(fn($entry) => [
                'entry'  => $entry,
                'source' => $entry->resolveEntryable(),
            ])
            ->filter(fn($item) => $item['source'] !== null)
            ->values();
    }

    /**
     * High significance entries (4-5) from the last 90 days.
     */
    public function getHighSignificanceProperty(): Collection
    {
        return TimelineEntry::forUser(auth()->id())
            ->minSignificance(4)
            ->where('date', '>=', now()->subDays(90))
            ->orderByDesc('date')
            ->limit(5)
            ->get()
            ->map(fn($entry) => [
                'entry'  => $entry,
                'source' => $entry->resolveEntryable(),
            ])
            ->filter(fn($item) => $item['source'] !== null)
            ->values();
    }

    /**
     * Upcoming incomplete tasks due within the window, plus overdue tasks.
     */
    public function getUpcomingTasksProperty(): Collection
    {
        return Task::where('user_id', auth()->id())
            ->incomplete()
            ->where('due_date', '<=', now()->addDays($this->upcomingDaysWindow))
            ->with('persons.primaryName')
            ->orderBy('due_date')
            ->get();
    }

    public function render()
    {
        return view('livewire.dashboard', [
            'upcomingKeyDates'      => $this->upcoming_key_dates,
            'unresolvedPrayerNeeds' => $this->unresolved_prayer_needs,
            'approachingGoals'      => $this->approaching_goals,
            'recentActivity'        => $this->recent_activity,
            'highSignificance'      => $this->high_significance,
            'upcomingTasks'         => $this->upcoming_tasks,
        ])->layout('layouts.app', ['title' => 'Dashboard — PastorEyes']);
    }
}