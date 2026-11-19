<?php

namespace App\Services\Api;

use App\Models\Goal;
use App\Models\KeyDate;
use App\Models\Note;
use App\Models\Outcome;
use App\Models\Person;
use App\Models\PrayerNeed;
use App\Models\Task;
use Illuminate\Database\Eloquent\Model;

/** Models to the plain arrays the API returns. One place, so field names stay stable. */
class ApiPresenter
{
    public const ENTRY_TYPES = ['note', 'prayer_need', 'goal', 'outcome', 'key_date'];

    /** The compact form used in lists and as a disambiguation candidate. */
    public static function summary(Person $p, PersonContext $c): array
    {
        $born = $c->birthday($p->id);

        return [
            'id'                  => $p->id,
            'display_name'        => $p->display_name,
            'born'                => $born['date'] ?? null,
            'born_year_unknown'   => $born['year_unknown'] ?? false,
            'age'                 => $c->age($p->id),
            'gender'              => $p->gender ?? 'unknown',
            'cities'              => $c->cities($p->id),
            'relationships'       => $c->relationshipLabels($p->id),
            'last_entry_date'     => $c->lastEntryDate($p->id),
            'active_prayer_needs' => $c->activePrayerNeeds($p->id),
        ];
    }

    /** A person with the sub-resources asked for (`include`). */
    public static function person(Person $p, PersonContext $c, array $include): array
    {
        $born  = $c->birthday($p->id);
        $death = $c->death($p->id);
        $has   = fn(string $what) => in_array($what, $include, true);

        $out = [
            'id'           => $p->id,
            'display_name' => $p->display_name,
            'gender'       => $p->gender ?? 'unknown',
            'born'         => $born ? $born + ['age' => $c->age($p->id)] : null,
            'died'         => $death,
        ];

        if ($has('about')) {
            $out['about'] = $p->notes;
        }
        if ($has('names')) {
            $out['names'] = $p->names->sortByDesc('is_primary')->values()->map(fn($n) => [
                'id' => $n->id, 'type' => $n->type, 'first_name' => $n->first_name, 'middle_names' => $n->middle_names,
                'last_name' => $n->last_name, 'preferred_name' => $n->preferred_name, 'is_primary' => $n->is_primary,
                'spelling_uncertain' => $n->spelling_uncertain, 'notes' => $n->notes,
            ])->all();
        }
        if ($has('addresses')) {
            $out['addresses'] = $c->addresses($p->id)->values()->map(fn($a) => [
                'id' => $a->id, 'line_1' => $a->line_1, 'line_2' => $a->line_2, 'line_3' => $a->line_3, 'city' => $a->city,
                'county' => $a->county, 'postcode' => $a->postcode, 'country' => $a->country,
                'is_current' => $a->is_current, 'date_added' => $a->date_added, 'notes' => $a->notes,
            ])->all();
        }
        if ($has('relationships')) {
            $out['relationships'] = $c->relationships($p->id)->map(function ($r) use ($p, $c) {
                $other = $c->person($r->person_id === $p->id ? $r->related_person_id : $r->person_id);
                return $other ? [
                    'id' => $r->id, 'type' => $r->relationshipType->name,
                    'label_from_this_person' => $r->labelForPerson($p->id) . ' of',
                    'person' => ['id' => $other->id, 'display_name' => $other->display_name],
                    'notes' => $r->notes,
                ] : null;
            })->filter()->values()->all();
        }
        if ($has('key_dates')) {
            $out['key_dates'] = $p->keyDates()->get()->map(fn(KeyDate $k) => self::keyDate($k))->all();
        }
        if ($has('active_prayer_needs')) {
            $out['active_prayer_needs'] = $p->prayerNeeds()->unresolved()->orderByDesc('significance')->get()
                ->map(fn(PrayerNeed $n) => ['entry_id' => 'prayer_need_' . $n->id, 'title' => $n->title,
                    'date' => $n->date->toDateString(), 'significance' => $n->significance])->all();
        }
        if ($has('goals')) {
            $out['goals'] = $p->goals()->active()->orderBy('target_date')->get()
                ->map(fn(Goal $g) => ['entry_id' => 'goal_' . $g->id, 'title' => $g->title,
                    'target_date' => $g->target_date?->toDateString(), 'significance' => $g->significance])->all();
        }
        if ($has('tasks')) {
            $out['tasks'] = $c->openTaskModels($p->id)->map(fn(Task $t) => self::task($t))->all();
        }
        if ($has('entries')) {
            $out['entries'] = (new EntryQuery($c->userId, $c))
                ->run(['person_id' => $p->id, 'per_page' => 10])['entries'];
        }

        return $out;
    }

    public static function keyDate(KeyDate $k): array
    {
        return [
            'id'               => $k->id,
            'entry_id'         => 'key_date_' . $k->id,
            'type'             => $k->type,
            'label'            => $k->label,
            'date'             => $k->date->toDateString(),
            'year_unknown'     => $k->year_unknown,
            'display'          => Narrator::date($k->date->toDateString(), $k->year_unknown),
            'is_recurring'     => $k->is_recurring,
            'recurrence_count' => $k->recurrence_count,
            'next_occurrence'  => $k->next_occurrence?->toDateString(),
            'days_until'       => $k->days_until,
        ];
    }

    /** One entry of any type, in the shape the Timeline uses (`note_5`, `prayer_need_12`...). */
    public static function entry(Model $e, string $type): array
    {
        $out = [
            'entry_id'     => "{$type}_{$e->id}",
            'type'         => $type,
            'date'         => $e->date->toDateString(),
            'logged_at'    => $e->logged_at?->utc()->toIso8601ZuluString(),
            'significance' => $e->significance,
            'title'        => $type === 'key_date' ? ($e->label ?: null) : $e->title,
            'body'         => $type === 'key_date' ? null : $e->body,
            'people'       => $e->persons->map(fn(Person $p) => [
                'id' => $p->id, 'display_name' => $p->display_name, 'is_primary' => (bool) $p->pivot->is_primary,
            ])->sortByDesc('is_primary')->values()->all(),
        ];

        if ($e->source) {
            $out['source'] = $e->source;
            $out['source_token_name'] = $e->source_token_name;
        }

        return $out + match ($type) {
            'prayer_need' => [
                'state' => $e->resolved_at ? 'resolved' : 'active',
                'resolved_at' => $e->resolved_at?->toDateString(),
                'resolution_details' => $e->resolution_details,
            ],
            'goal' => [
                'state' => $e->achieved_at ? 'resolved' : 'active',
                'target_date' => $e->target_date?->toDateString(),
                'achieved_at' => $e->achieved_at?->toDateString(),
                'outcome_count' => $e->outcomes->count(),
            ],
            'outcome' => ['goal_entry_id' => $e->goal_id ? 'goal_' . $e->goal_id : null],
            'key_date' => [
                'key_date_type' => $e->type, 'is_recurring' => $e->is_recurring, 'year_unknown' => $e->year_unknown,
                'recurrence_count' => $e->recurrence_count,
            ],
            default => [],
        };
    }

    public static function task(Task $t): array
    {
        $out = [
            'id'        => $t->id,
            'title'     => $t->title,
            'narrative' => $t->narrative,
            'due_date'  => $t->due_date->toDateString(),
            'status'    => $t->is_complete ? 'completed' : 'active',
            'overdue'   => !$t->is_complete && $t->due_date->lt(now()->startOfDay()),
            'people'    => $t->persons->map(fn(Person $p) => ['id' => $p->id, 'display_name' => $p->display_name])->values()->all(),
        ];

        if ($t->source) {
            $out['source'] = $t->source;
            $out['source_token_name'] = $t->source_token_name;
        }

        return $out;
    }

    public static function typeLabel(string $type): string
    {
        return config("entry_types.types.{$type}.label", $type);
    }

    /** The model class behind an entry type. */
    public static function modelFor(string $type): string
    {
        return config("entry_types.types.{$type}.model");
    }
}
