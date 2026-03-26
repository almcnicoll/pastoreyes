<?php

namespace App\Livewire;

use App\Models\TimelineEntry;
use Illuminate\Support\Collection;
use Livewire\Component;

class Timeline extends Component
{
    // Optional: if set, timeline is scoped to a single person
    public ?int $personId = null;

    // Filters
    public array $filterTypes = [];
    public array $filterSignificance = [];
    public ?string $dateFrom = null;
    public ?string $dateTo = null;
    public string $sortDirection = 'desc';

    // Inline entry expansion
    public ?string $expandedEntryId = null;

    protected $listeners = [
        'timeline-updated' => '$refresh',
    ];

    public function mount(?int $personId = null): void
    {
        $this->personId = $personId;
    }

    public function toggleSort(): void
    {
        $this->sortDirection = $this->sortDirection === 'desc' ? 'asc' : 'desc';
    }

    public function toggleType(string $type): void
    {
        if (in_array($type, $this->filterTypes)) {
            $this->filterTypes = array_values(array_diff($this->filterTypes, [$type]));
        } else {
            $this->filterTypes[] = $type;
        }
    }

    public function toggleSignificance(int $sig): void
    {
        if (in_array($sig, $this->filterSignificance)) {
            $this->filterSignificance = array_values(array_diff($this->filterSignificance, [$sig]));
        } else {
            $this->filterSignificance[] = $sig;
        }
    }

    public function clearFilters(): void
    {
        $this->filterTypes        = [];
        $this->filterSignificance = [];
        $this->dateFrom           = null;
        $this->dateTo             = null;
    }

    public function toggleExpand(string $entryId): void
    {
        $this->expandedEntryId = $this->expandedEntryId === $entryId ? null : $entryId;
    }

    public function getEntriesProperty(): Collection
    {
        $query = TimelineEntry::forUser(auth()->id());

        if ($this->personId) {
            $query->forPerson($this->personId);
        }

        if (!empty($this->filterTypes)) {
            $query->ofType($this->filterTypes);
        }

        if (!empty($this->filterSignificance)) {
            $query->whereIn('significance', $this->filterSignificance);
        }

        if ($this->dateFrom) {
            $query->where('date', '>=', $this->dateFrom);
        }

        if ($this->dateTo) {
            $query->where('date', '<=', $this->dateTo);
        }

        return $query->orderBy('date', $this->sortDirection)->get();
    }

    public function render()
    {
        $entries = $this->entries->map(function ($entry) {
            return [
                'entry'  => $entry,
                'source' => $entry->resolveEntryable(),
            ];
        })->filter(fn($item) => $item['source'] !== null)->values();

        return view('livewire.timeline', [
            'entries'    => $entries,
            'entryTypes' => config('entry_types.types'),
        ])->layout(
            $this->personId ? null : 'layouts.app',
            $this->personId ? [] : ['title' => 'Timeline — PastorEyes']
        );
    }
}
