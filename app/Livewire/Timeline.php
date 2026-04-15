<?php

namespace App\Livewire;

use App\Models\Goal;
use App\Models\KeyDate;
use App\Models\Note;
use App\Models\Outcome;
use App\Models\Person;
use App\Models\PrayerNeed;
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

    // Edit form state
    public bool $showEditForm = false;
    public ?string $editingEntryId = null;
    public string $editType = '';
    public ?int $editSourceId = null;

    // Common edit fields
    public string $editTitle = '';
    public string $editBody = '';
    public int $editSignificance = 3;
    public string $editDate = '';

    // PrayerNeed-specific
    public ?string $editResolvedAt = null;
    public string $editResolutionDetails = '';

    // Goal-specific
    public ?string $editTargetDate = null;
    public ?string $editAchievedAt = null;

    // KeyDate-specific
    public string $editKeyDateType = 'birthday';
    public string $editLabel = '';
    public bool $editIsRecurring = true;
    public bool $editYearUnknown = false;

    // Person links for edit
    public array $editSelectedPersonIds = [];
    public string $editPersonSearch = '';
    public $editPersonResults;

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

    public function openEdit(string $entryId): void
    {
        $entry = TimelineEntry::forUser(auth()->id())->find($entryId);
        if (!$entry) {
            return;
        }

        $source = $entry->resolveEntryable();
        if (!$source) {
            return;
        }

        $this->editingEntryId = $entryId;
        $this->editType       = $entry->type;
        $this->editSourceId   = $source->id;

        // Common fields
        $this->editDate        = $entry->date->format('Y-m-d');
        $this->editSignificance = $entry->significance;
        $this->editTitle       = $source->title ?? '';
        $this->editBody        = $source->body ?? '';

        // PrayerNeed-specific
        $this->editResolvedAt        = $source->resolved_at?->format('Y-m-d') ?? null;
        $this->editResolutionDetails = $source->resolution_details ?? '';

        // Goal-specific
        $this->editTargetDate = $source->target_date?->format('Y-m-d') ?? null;
        $this->editAchievedAt = $source->achieved_at?->format('Y-m-d') ?? null;

        // KeyDate-specific
        $this->editKeyDateType = $source->type ?? 'birthday';
        $this->editLabel       = $source->label ?? '';
        $this->editIsRecurring = (bool) ($source->is_recurring ?? false);
        $this->editYearUnknown = (bool) ($source->year_unknown ?? false);

        // Person links
        $this->editSelectedPersonIds = $source->persons->pluck('id')->toArray();
        $this->editPersonSearch      = '';
        $this->editPersonResults     = collect();

        $this->showEditForm = true;
    }

    public function cancelEdit(): void
    {
        $this->showEditForm       = false;
        $this->editingEntryId     = null;
        $this->editType           = '';
        $this->editSourceId       = null;
        $this->editTitle          = '';
        $this->editBody           = '';
        $this->editSignificance   = 3;
        $this->editDate           = '';
        $this->editResolvedAt     = null;
        $this->editResolutionDetails = '';
        $this->editTargetDate     = null;
        $this->editAchievedAt     = null;
        $this->editKeyDateType    = 'birthday';
        $this->editLabel          = '';
        $this->editIsRecurring    = true;
        $this->editYearUnknown    = false;
        $this->editSelectedPersonIds = [];
        $this->editPersonSearch   = '';
        $this->editPersonResults  = collect();
    }

    public function saveEdit(): void
    {
        $rules = [
            'editDate'        => 'required|date',
            'editSignificance' => 'required|integer|min:1|max:5',
        ];

        if (in_array($this->editType, ['note', 'prayer_need', 'goal', 'outcome'])) {
            $rules['editBody'] = 'required|string';
        }

        if ($this->editType === 'goal') {
            $rules['editTargetDate'] = 'nullable|date';
            $rules['editAchievedAt'] = 'nullable|date';
        }

        if ($this->editType === 'prayer_need') {
            $rules['editResolvedAt']        = 'nullable|date';
            $rules['editResolutionDetails'] = 'nullable|string';
        }

        if ($this->editType === 'key_date') {
            $rules['editKeyDateType'] = 'required|in:birthday,wedding_anniversary,bereavement,other';
            $rules['editLabel']       = 'nullable|string|max:255';
        }

        $this->validate($rules);

        $source = match($this->editType) {
            'note'        => Note::find($this->editSourceId),
            'prayer_need' => PrayerNeed::find($this->editSourceId),
            'goal'        => Goal::find($this->editSourceId),
            'outcome'     => Outcome::find($this->editSourceId),
            'key_date'    => KeyDate::find($this->editSourceId),
            default       => null,
        };

        if (!$source || $source->user_id !== auth()->id()) {
            return;
        }

        $common = [
            'date'         => $this->editDate,
            'significance' => $this->editSignificance,
        ];

        $typeSpecific = match($this->editType) {
            'note' => [
                'title' => $this->editTitle ?: null,
                'body'  => $this->editBody,
            ],
            'prayer_need' => [
                'title'              => $this->editTitle ?: null,
                'body'               => $this->editBody,
                'resolved_at'        => $this->editResolvedAt ?: null,
                'resolution_details' => $this->editResolutionDetails ?: null,
            ],
            'goal' => [
                'title'       => $this->editTitle ?: null,
                'body'        => $this->editBody,
                'target_date' => $this->editTargetDate ?: null,
                'achieved_at' => $this->editAchievedAt ?: null,
            ],
            'outcome' => [
                'title' => $this->editTitle ?: null,
                'body'  => $this->editBody,
            ],
            'key_date' => [
                'type'         => $this->editKeyDateType,
                'label'        => $this->editLabel ?: null,
                'is_recurring' => $this->editIsRecurring,
                'year_unknown' => $this->editYearUnknown,
            ],
            default => [],
        };

        $source->update(array_merge($common, $typeSpecific));

        // Sync persons
        $syncData = [];
        foreach ($this->editSelectedPersonIds as $i => $pid) {
            $syncData[$pid] = ['is_primary' => $i === 0];
        }
        $source->persons()->sync($syncData);

        $this->cancelEdit();
        $this->dispatch('notify', message: 'Entry updated.');
        $this->dispatch('timeline-updated');
    }

    public function updatedEditPersonSearch(): void
    {
        if (strlen($this->editPersonSearch) < 1) {
            $this->editPersonResults = collect();
            return;
        }

        $search          = strtolower($this->editPersonSearch);
        $alreadySelected = $this->editSelectedPersonIds;

        $this->editPersonResults = Person::where('user_id', auth()->id())
            ->with('primaryName')
            ->get()
            ->filter(fn($p) =>
                str_contains(strtolower($p->display_name), $search) &&
                !in_array($p->id, $alreadySelected)
            )
            ->take(8)
            ->values();
    }

    public function addEditPerson(int $personId): void
    {
        if (!in_array($personId, $this->editSelectedPersonIds)) {
            $this->editSelectedPersonIds[] = $personId;
        }
        $this->editPersonSearch  = '';
        $this->editPersonResults = collect();
    }

    public function removeEditPerson(int $personId): void
    {
        $this->editSelectedPersonIds = array_values(
            array_filter($this->editSelectedPersonIds, fn($id) => $id !== $personId)
        );
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

        $editSelectedPersons = empty($this->editSelectedPersonIds)
            ? collect()
            : Person::whereIn('id', $this->editSelectedPersonIds)->get()
                ->sortBy(fn($p) => array_search($p->id, $this->editSelectedPersonIds));

        return view('livewire.timeline', [
            'entries'             => $entries,
            'entryTypes'          => config('entry_types.types'),
            'editSelectedPersons' => $editSelectedPersons,
        ])->layout(
            $this->personId ? null : 'layouts.app',
            $this->personId ? [] : ['title' => 'Timeline — PastorEyes']
        );
    }
}
