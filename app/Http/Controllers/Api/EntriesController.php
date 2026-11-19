<?php

namespace App\Http\Controllers\Api;

use App\Actions\CreateEntry;
use App\Actions\EntryRules;
use App\Actions\RecordOutcome;
use App\Actions\ResolvePrayerNeed;
use App\Actions\SyncKeyDateToCalendar;
use App\Models\PrayerNeed;
use App\Services\Api\ApiPresenter;
use App\Services\Api\ApiResponse;
use App\Services\Api\EntryQuery;
use App\Services\Api\Narrator;
use App\Services\PersonSearch;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EntriesController extends ApiController
{
    /** Outcomes are created on their Goal (POST /entries/{goal}/outcomes), not here. */
    private const CREATABLE = ['note', 'prayer_need', 'goal', 'key_date'];

    public function index(Request $request): JsonResponse
    {
        $this->input($request, [
            'person' => 'nullable', 'type' => 'nullable', 'active' => 'nullable|in:true,false,1,0',
            'significance' => 'nullable', 'min_significance' => 'nullable|integer|min:1|max:5',
            'from' => 'nullable|date', 'to' => 'nullable|date', 'q' => 'nullable|string|max:200',
            'sort' => 'nullable|in:newest,oldest', 'page' => 'nullable|integer|min:1', 'per_page' => 'nullable|integer|min:1|max:100',
        ], doing: 'list entries');

        $filters = [
            'types'            => $this->listParam($request, 'type', ApiPresenter::ENTRY_TYPES),
            'significance'     => array_map('intval', $this->listParam($request, 'significance', ['1', '2', '3', '4', '5'])),
            'active'           => $request->boolean('active'),
            'min_significance' => $request->query('min_significance'),
            'from'             => $request->query('from'),
            'to'               => $request->query('to'),
            'q'                => $request->query('q'),
            'sort'             => $request->query('sort', 'newest'),
        ];
        $filters['types'] = $filters['types'] ?: null;

        $person = null;
        if ($ref = $this->personRefFromQuery($request)) {
            $person = $this->person($ref, write: false);
            $filters['person_id'] = $person->id;
        }

        [$page, $perPage] = $this->paging($request);
        $result = (new EntryQuery($this->userId(), $this->context()))->run($filters + ['page' => $page, 'per_page' => $perPage]);

        $typeLabel = count($filters['types'] ?? []) === 1 ? strtolower(ApiPresenter::typeLabel($filters['types'][0])) . 's' : 'entries';
        $message   = Narrator::entries($result['total'], count($result['entries']), [
            'person' => $person?->display_name, 'type_label' => ($filters['active'] ? 'active ' : '') . $typeLabel,
        ]);
        if ($result['truncated']) {
            $message .= ' Only the newest ' . config('api.text_search_scan_cap') . ' entries were scanned for that text: narrow it with a person or dates for a complete search.';
        }

        $this->summarise('listed entries');

        return ApiResponse::ok($message, $result['entries'], $this->warnings(),
            meta: $this->pagination($result['total'], $page, $perPage) + ($result['truncated'] ? ['truncated' => true] : []));
    }

    public function show(string $entry_id): JsonResponse
    {
        [$type, $entry] = $this->entry($entry_id);

        $this->summarise('read one entry');

        return ApiResponse::ok($this->describe($type, $entry) . '.', ApiPresenter::entry($entry, $type));
    }

    public function store(Request $request): JsonResponse
    {
        $type = $request->input('type');
        if ($type === 'outcome') {
            return ApiResponse::invalid("An Outcome belongs to a Goal: POST /api/v1/entries/{goal entry_id}/outcomes instead.");
        }
        if (!in_array($type, self::CREATABLE, true)) {
            return ApiResponse::invalid("I couldn't add that entry: type must be one of " . implode(', ', self::CREATABLE) . '.');
        }

        // Defaults an agent shouldn't have to spell out
        $defaults = $type === 'key_date'
            ? ['significance' => 3, 'is_recurring' => true]
            : ['significance' => 3, 'date' => $this->today()];
        $request->merge(array_diff_key($defaults, $request->all()) + $request->all());

        $input = $this->input(
            $request,
            $this->rulesFor($type) + ['type' => 'required', 'people' => 'required|array|min:1|max:50', 'people.*' => 'required'],
            ['confirm_duplicate', 'sync_to_calendar'],
            'add that entry'
        );

        $people = $this->people($input['people'], write: true);
        $attrs  = $this->attributes($type, $input);

        if (!$request->boolean('confirm_duplicate') && ($dupe = $this->duplicateOf($type, $people[0]->id, $attrs))) {
            return ApiResponse::make('possible_duplicate',
                'This looks like an entry already recorded for ' . $people[0]->display_name . ' (' . $this->describe($type, $dupe)
                . '). Nothing was saved. If it really is a new entry, resend with confirm_duplicate: true.',
                ['existing' => ApiPresenter::entry($dupe, $type)], 409);
        }

        $summaries = array_map(fn($p) => ApiPresenter::summary($p, $this->context()), $people);
        $label     = ApiPresenter::typeLabel($type);
        $message   = Narrator::entryAdded($label, $attrs['title'] ?? $attrs['label'] ?? null, Narrator::date($attrs['date'], $attrs['year_unknown'] ?? false), $summaries, ApiResponse::isDryRun());

        if (ApiResponse::isDryRun()) {
            return ApiResponse::ok($message, ['type' => $type, 'people' => array_column($summaries, 'id')], $this->warnings());
        }

        $entry = (new CreateEntry)->execute($this->userId(), $type, array_column($summaries, 'id'), $attrs, $this->provenance());

        if ($type === 'key_date' && $request->boolean('sync_to_calendar')) {
            try {
                (new SyncKeyDateToCalendar)->execute($entry) || $this->warn('The key date was saved but could not be synced to Google Calendar (check the Google connection in Settings).');
            } catch (\Throwable) {
                $this->warn('The key date was saved but could not be synced to Google Calendar.');
            }
        }

        $this->summarise("created {$label} for " . Narrator::plural(count($people), 'person', 'people'));

        return ApiResponse::created($message, [
            'entry_id' => "{$type}_{$entry->id}",
            'type'     => $type,
            'people'   => array_map(fn($p, $i) => ['id' => $p['id'], 'is_primary' => $i === 0], $summaries, array_keys($summaries)),
        ], $this->warnings(), match ($type) {
            'prayer_need' => [['hint' => 'Mark the prayer need as resolved later', 'method' => 'POST', 'path' => "/api/v1/entries/prayer_need_{$entry->id}/resolve"]],
            'goal'        => [['hint' => 'Record an outcome on this goal', 'method' => 'POST', 'path' => "/api/v1/entries/goal_{$entry->id}/outcomes"]],
            default       => [['hint' => 'Add a prayer need for the same person', 'method' => 'POST', 'path' => '/api/v1/entries']],
        });
    }

    public function update(Request $request, string $entry_id): JsonResponse
    {
        [$type, $entry] = $this->entry($entry_id);

        $rules = collect($this->rulesFor($type))->map(fn($r) => is_array($r) ? ['sometimes', ...$r] : "sometimes|{$r}")->all();
        $input = $this->input($request, $rules + ['people' => 'sometimes|array|min:1|max:50', 'people.*' => 'required'],
            $type === 'key_date' ? ['sync_to_calendar'] : [], 'update that entry');

        $people = isset($input['people']) ? $this->people($input['people'], write: true) : null;
        $attrs  = $this->attributes($type, $input);
        if (array_key_exists('is_recurring', $attrs) && !$attrs['is_recurring']) {
            $attrs['recurrence_count'] = null;
        }

        $changed = array_merge(array_keys($attrs), $people ? ['people'] : []);

        if (ApiResponse::isDryRun()) {
            return ApiResponse::ok("Would update {$entry_id}: " . (Narrator::join($changed) ?: 'nothing') . '.', null, $this->warnings());
        }

        $entry->update($attrs);
        if ($people) {
            $entry->persons()->sync(collect($people)->values()->mapWithKeys(fn($p, $i) => [$p->id => ['is_primary' => $i === 0]])->all());
        }

        $this->summarise("updated {$type}");

        [, $fresh] = $this->entry($entry_id);

        return ApiResponse::ok('Updated ' . $this->describe($type, $fresh) . ': ' . (Narrator::join($changed) ?: 'nothing changed') . '.',
            ApiPresenter::entry($fresh, $type), $this->warnings());
    }

    public function destroy(Request $request, string $entry_id): JsonResponse
    {
        $this->input($request, [], doing: 'delete that entry');
        [$type, $entry] = $this->entry($entry_id);

        $outcomes = $type === 'goal' ? $entry->outcomes->count() : 0;
        $warning  = 'Delete this ' . ApiPresenter::typeLabel($type) . ' (' . $this->describe($type, $entry, false) . ')?'
                  . ($outcomes ? ' Its ' . Narrator::plural($outcomes, 'outcome') . ($outcomes === 1 ? ' is' : ' are') . ' deleted too.' : '')
                  . ' This cannot be undone.';

        if ($response = $this->confirmDelete($request, $entry_id, $warning)) {
            return $response;
        }

        $entry->delete();
        $this->summarise("deleted {$type}");

        return ApiResponse::ok('Deleted the ' . ApiPresenter::typeLabel($type) . ($outcomes ? ' and its ' . Narrator::plural($outcomes, 'outcome') : '') . '.');
    }

    public function resolve(Request $request, string $entry_id): JsonResponse
    {
        $input = $this->input($request, ['resolved_at' => 'nullable|date', 'resolution_details' => 'nullable|string'], doing: 'resolve that prayer need');
        $need  = $this->prayerNeed($entry_id, 'resolved');

        if ($need->resolved_at) {
            $this->warn('It was already resolved (on ' . Narrator::date($need->resolved_at->toDateString()) . '); the date and details have been replaced.');
        }

        $message = 'Marked ' . $this->describe('prayer_need', $need) . ' as resolved.';
        if (ApiResponse::isDryRun()) {
            return ApiResponse::ok('Would mark ' . $this->describe('prayer_need', $need) . ' as resolved.', null, $this->warnings());
        }

        (new ResolvePrayerNeed)->resolve($need, $input['resolved_at'] ?? $this->today(), $input['resolution_details'] ?? null);
        $this->summarise('resolved a prayer need');

        return ApiResponse::ok($message, ApiPresenter::entry($need->fresh('persons.primaryName'), 'prayer_need'), $this->warnings());
    }

    public function reopen(Request $request, string $entry_id): JsonResponse
    {
        $this->input($request, [], doing: 'reopen that prayer need');
        $need = $this->prayerNeed($entry_id, 'reopened');

        if (!$need->resolved_at) {
            $this->warn('It was already active, so nothing changed.');
        }

        if (!ApiResponse::isDryRun() && $need->resolved_at) {
            (new ResolvePrayerNeed)->reopen($need);
            $this->summarise('reopened a prayer need');
        }

        return ApiResponse::ok((ApiResponse::isDryRun() ? 'Would reopen ' : 'Reopened ') . $this->describe('prayer_need', $need) . '.',
            ApiPresenter::entry($need->fresh('persons.primaryName'), 'prayer_need'), $this->warnings());
    }

    public function outcome(Request $request, string $entry_id): JsonResponse
    {
        $request->merge(array_diff_key(['significance' => 3], $request->all()) + $request->all());
        $input = $this->input($request, [
            'title'         => EntryRules::TITLE_REQUIRED,
            'body'          => EntryRules::BODY,
            'significance'  => EntryRules::SIGNIFICANCE,
            'goal_complete' => 'boolean',
            'achieved_at'   => 'nullable|date',
        ], doing: 'record that outcome');

        [$type, $goal] = $this->entry($entry_id);
        if ($type !== 'goal') {
            return ApiResponse::invalid('Outcomes are recorded on a Goal, but ' . $entry_id . ' is a ' . ApiPresenter::typeLabel($type) . '.');
        }

        $complete = (bool) ($input['goal_complete'] ?? false);
        if ($complete && $goal->achieved_at) {
            $this->warn('That Goal was already achieved (on ' . Narrator::date($goal->achieved_at->toDateString()) . ').');
        }

        $about   = $this->describe('goal', $goal);
        $message = ApiResponse::isDryRun() ? 'Would record' : 'Recorded';
        $message .= " an Outcome '{$input['title']}' on {$about}" . ($complete ? ' and mark the Goal achieved.' : '.');

        if (ApiResponse::isDryRun()) {
            return ApiResponse::ok($message, null, $this->warnings());
        }

        $outcome = (new RecordOutcome)->execute($goal, $input['title'], $input['body'], (int) $input['significance'], $complete,
            $input['achieved_at'] ?? ($complete ? $this->today() : null), $this->today(), $this->provenance());

        $this->summarise('recorded an outcome');

        return ApiResponse::created($message, ['entry_id' => "outcome_{$outcome->id}", 'goal_entry_id' => $entry_id, 'goal_achieved' => $complete || (bool) $goal->achieved_at], $this->warnings());
    }

    // -------------------------------------------------------------------------

    /** Field rules in API names: a Key Date's own type is `key_date_type`, since `type` is the entry type. */
    private function rulesFor(string $type): array
    {
        $rules = EntryRules::fields($type);

        if ($type === 'key_date') {
            $rules['key_date_type'] = $rules['type'];
            unset($rules['type']);
        }

        return $rules;
    }

    /** API fields to the model's columns. */
    private function attributes(string $type, array $input): array
    {
        $attrs = array_intersect_key($input, EntryRules::fields($type) + ['key_date_type' => 1]);

        if (array_key_exists('key_date_type', $attrs)) {
            $attrs['type'] = $attrs['key_date_type'];
            unset($attrs['key_date_type']);
        }
        if ($type === 'key_date' && array_key_exists('is_recurring', $attrs) && !$attrs['is_recurring']) {
            $attrs['recurrence_count'] = null;
        }

        return $attrs;
    }

    /** A probable duplicate: same type and primary person, within a week, with the same text (or, for a Key Date, kind and date). */
    private function duplicateOf(string $type, int $primaryPersonId, array $attrs): ?Model
    {
        $date = Carbon::parse($attrs['date']);

        return ApiPresenter::modelFor($type)::where('user_id', $this->userId())
            ->whereHas('persons', fn($q) => $q->where('persons.id', $primaryPersonId)->where('person_entry.is_primary', true))
            ->whereDate('date', '>=', $date->copy()->subDays(7)->toDateString())
            ->whereDate('date', '<=', $date->copy()->addDays(7)->toDateString())
            ->with($type === 'goal' ? ['persons.primaryName', 'outcomes'] : ['persons.primaryName'])
            ->get()
            ->first(function ($e) use ($type, $attrs, $date) {
                if ($type === 'key_date') {
                    return $e->type === ($attrs['type'] ?? null) && $e->date->isSameDay($date);
                }
                $same = fn($a, $b) => PersonSearch::norm($a) === PersonSearch::norm($b);

                return $same($e->title, $attrs['title'] ?? '') && $same($e->body, $attrs['body'] ?? '');
            });
    }

    /** "the Note 'Hospital visit' about Al McNicoll, 18 Nov 2026" */
    private function describe(string $type, Model $e, bool $article = true): string
    {
        $title  = $type === 'key_date' ? ($e->label ?: $e->type_label) : $e->title;
        $people = $e->persons->map(fn($p) => $p->display_name)->all();

        return ($article ? 'the ' : '') . ApiPresenter::typeLabel($type) . ($title ? " '{$title}'" : '')
            . ($people ? ' about ' . Narrator::join($people) : '')
            . ', ' . Narrator::date($e->date->toDateString(), (bool) ($e->year_unknown ?? false));
    }

    private function prayerNeed(string $entryId, string $doing): PrayerNeed
    {
        [$type, $entry] = $this->entry($entryId);

        if ($type !== 'prayer_need') {
            $hint = $type === 'goal' ? ' A Goal is finished by recording an Outcome with goal_complete: true.' : '';
            $this->halt(ApiResponse::invalid("Only a Prayer Need can be {$doing}, but {$entryId} is a " . ApiPresenter::typeLabel($type) . ".{$hint}"));
        }

        return $entry;
    }

    /** `type=note,goal`, `type[]=note&type[]=goal` or a single value, checked against the allowed ones. */
    private function listParam(Request $request, string $key, array $allowed): array
    {
        $raw    = $request->query($key);
        $values = collect(is_array($raw) ? $raw : explode(',', (string) $raw))->map(fn($v) => trim((string) $v))->filter()->values()->all();

        if ($bad = array_diff($values, $allowed)) {
            $this->halt(ApiResponse::invalid("Unknown {$key}: " . implode(', ', $bad) . '. Use ' . implode(', ', $allowed) . '.'));
        }

        return $values;
    }
}
