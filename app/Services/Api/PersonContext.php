<?php

namespace App\Services\Api;

use App\Models\Address;
use App\Models\KeyDate;
use App\Models\Person;
use App\Models\Relationship;
use App\Models\Task;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Everything the API needs to know *about* a user's people, loaded in a handful of batch
 * queries and held for one request: names, birth/death dates, active prayer needs, last entry,
 * open tasks, cities and relationships. Per-person lookups are then array reads, not queries.
 *
 * Nothing is persisted: names and addresses are encrypted, so this index lives and dies with the request.
 */
class PersonContext
{
    private ?Collection $persons = null;
    private array $cache = [];

    public function __construct(public readonly int $userId) {}

    /** @return Collection<int, Person> keyed by id, with names loaded */
    public function persons(): Collection
    {
        return $this->persons ??= Person::where('user_id', $this->userId)
            ->with(['primaryName', 'names'])
            ->get()
            ->keyBy('id');
    }

    public function person(int $id): ?Person
    {
        return $this->persons()->get($id);
    }

    private function memo(string $key, callable $load): mixed
    {
        return $this->cache[$key] ??= $load();
    }

    // -------------------------------------------------------------------------
    // Dates
    // -------------------------------------------------------------------------

    /** @return array{date: string, year_unknown: bool}|null */
    public function birthday(int $personId): ?array
    {
        return $this->keyDates('birthday')[$personId] ?? null;
    }

    /** @return array{date: string, year_unknown: bool}|null */
    public function death(int $personId): ?array
    {
        return $this->keyDates('death')[$personId] ?? null;
    }

    /** First key date of a type per person. The date columns aren't encrypted, so no models are needed. */
    private function keyDates(string $type): array
    {
        return $this->memo("kd:$type", function () use ($type) {
            return DB::table('person_entry')
                ->join('key_dates', 'key_dates.id', '=', 'person_entry.entryable_id')
                ->where('person_entry.entryable_type', KeyDate::class)
                ->where('key_dates.user_id', $this->userId)
                ->where('key_dates.type', $type)
                ->orderBy('key_dates.id')
                ->get(['person_entry.person_id', 'key_dates.date', 'key_dates.year_unknown'])
                ->unique('person_id')
                ->mapWithKeys(fn($r) => [$r->person_id => [
                    'date'         => substr($r->date, 0, 10),
                    'year_unknown' => (bool) $r->year_unknown,
                ]])
                ->all();
        });
    }

    public function age(int $personId): ?int
    {
        $born = $this->birthday($personId);
        if (!$born || $born['year_unknown']) {
            return null;
        }

        $until = $this->death($personId);
        $end   = $until && !$until['year_unknown'] ? \Carbon\Carbon::parse($until['date']) : now();

        return (int) \Carbon\Carbon::parse($born['date'])->diffInYears($end);
    }

    // -------------------------------------------------------------------------
    // Counts and last activity
    // -------------------------------------------------------------------------

    public function activePrayerNeeds(int $personId): int
    {
        return $this->memo('needs', fn() => DB::table('person_entry')
            ->join('prayer_needs', 'prayer_needs.id', '=', 'person_entry.entryable_id')
            ->where('person_entry.entryable_type', \App\Models\PrayerNeed::class)
            ->where('prayer_needs.user_id', $this->userId)
            ->whereNull('prayer_needs.resolved_at')
            ->groupBy('person_entry.person_id')
            ->selectRaw('person_entry.person_id as person_id, count(*) as total')
            ->pluck('total', 'person_id')
            ->all())[$personId] ?? 0;
    }

    /** Latest date among a person's notes, prayer needs, goals and outcomes (key dates aren't contact). */
    public function lastEntryDate(int $personId): ?string
    {
        return $this->memo('last', function () {
            $latest = [];
            foreach (['notes' => \App\Models\Note::class, 'prayer_needs' => \App\Models\PrayerNeed::class,
                      'goals' => \App\Models\Goal::class, 'outcomes' => \App\Models\Outcome::class] as $table => $class) {
                DB::table('person_entry')
                    ->join($table, "$table.id", '=', 'person_entry.entryable_id')
                    ->where('person_entry.entryable_type', $class)
                    ->where("$table.user_id", $this->userId)
                    ->groupBy('person_entry.person_id')
                    ->selectRaw("person_entry.person_id as person_id, max($table.date) as latest")
                    ->get()
                    ->each(function ($r) use (&$latest) {
                        $d = substr($r->latest, 0, 10);
                        if (!isset($latest[$r->person_id]) || $d > $latest[$r->person_id]) {
                            $latest[$r->person_id] = $d;
                        }
                    });
            }
            return $latest;
        })[$personId] ?? null;
    }

    public function openTasks(int $personId): int
    {
        return $this->memo('tasks', fn() => DB::table('person_task')
            ->join('tasks', 'tasks.id', '=', 'person_task.task_id')
            ->where('tasks.user_id', $this->userId)
            ->where('tasks.is_complete', false)
            ->groupBy('person_task.person_id')
            ->selectRaw('person_task.person_id as person_id, count(*) as total')
            ->pluck('total', 'person_id')
            ->all())[$personId] ?? 0;
    }

    // -------------------------------------------------------------------------
    // Addresses and relationships
    // -------------------------------------------------------------------------

    /** @return Collection<int, Address> */
    public function addresses(int $personId): Collection
    {
        return $this->memo('addresses', fn() => Address::whereIn('person_id', $this->persons()->keys())
            ->get()
            ->sortByDesc('is_current')
            ->groupBy('person_id'))->get($personId, collect());
    }

    /** Distinct cities, current address first. */
    public function cities(int $personId): array
    {
        return $this->addresses($personId)->pluck('city')->filter()->unique()->values()->all();
    }

    /** @return Collection<int, Relationship> every relationship the person is on either end of */
    public function relationships(int $personId): Collection
    {
        return $this->memo('rels', fn() => Relationship::where('user_id', $this->userId)->with('relationshipType')->get())
            ->filter(fn($r) => $r->person_id === $personId || $r->related_person_id === $personId)
            ->values();
    }

    /** "spouse of Caroline McNicoll": the person's own role, then the other person. */
    public function relationshipLabels(int $personId): array
    {
        return $this->relationships($personId)->map(function (Relationship $r) use ($personId) {
            $other = $this->person($r->person_id === $personId ? $r->related_person_id : $r->person_id);
            return $other ? $r->labelForPerson($personId) . ' of ' . $other->display_name : null;
        })->filter()->values()->all();
    }

    /** Open tasks plus anything else a caller wants to know in bulk is added here, not scattered. */
    public function openTaskModels(int $personId): Collection
    {
        return Task::where('user_id', $this->userId)->incomplete()
            ->whereHas('persons', fn($q) => $q->where('persons.id', $personId))
            ->orderBy('due_date')->get();
    }
}
