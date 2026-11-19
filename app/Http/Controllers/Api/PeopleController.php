<?php

namespace App\Http\Controllers\Api;

use App\Actions\SavePersonKeyDate;
use App\Models\Address;
use App\Models\Person;
use App\Models\PersonName;
use App\Services\Api\ApiPresenter;
use App\Services\Api\ApiResponse;
use App\Services\Api\EntryQuery;
use App\Services\Api\Narrator;
use App\Services\PersonSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PeopleController extends ApiController
{
    private const INCLUDES = ['names', 'addresses', 'relationships', 'key_dates', 'entries', 'active_prayer_needs', 'goals', 'tasks', 'about'];

    /** POST /people/resolve: who does this refer to? No side effects. */
    public function resolve(Request $request): JsonResponse
    {
        $body = $request->isJson() ? $request->json()->all() : $request->request->all();

        // { people: [ref, ...] } for several, otherwise the body itself is one PersonRef
        if (array_key_exists('people', $body)) {
            if (!is_array($body['people']) || !$body['people'] || array_diff(array_keys($body), ['people', 'dry_run'])) {
                return ApiResponse::invalid('Send { "people": [PersonRef, ...] } with at least one person and nothing else.');
            }

            $many = $this->resolver()->resolveMany($body['people']);
            if (!$many['ok']) {
                return ApiResponse::problems($many['results'], $this->context(), false);
            }

            $people = array_map(fn($p) => ApiPresenter::summary($p, $this->context()), $many['people']);

            return ApiResponse::ok(
                'Found ' . Narrator::join(array_map([Narrator::class, 'label'], $people)) . '.',
                ['people' => $people], ApiResponse::resolutionWarnings($many['results'])
            );
        }

        $result = $this->resolver()->resolve($body['person'] ?? $body);
        if ($result['status'] !== 'ok') {
            return ApiResponse::unresolved($result, $this->context(), false);
        }

        $summary = ApiPresenter::summary($result['person'], $this->context());

        return ApiResponse::ok('Found ' . Narrator::label($summary) . '.', ['person' => $summary], ApiResponse::resolutionWarnings([$result]));
    }

    public function index(Request $request): JsonResponse
    {
        $this->input($request, [
            'q'                       => 'nullable|string|max:200',
            'born'                    => 'nullable|string|max:10',
            'city'                    => 'nullable|string|max:100',
            'gender'                  => 'nullable|in:male,female,unknown',
            'has_active_prayer_needs' => 'nullable|in:true,false,1,0',
            'related_to'              => 'nullable|integer',
            'sort'                    => 'nullable|in:name,recent',
            'page'                    => 'nullable|integer|min:1',
            'per_page'                => 'nullable|integer|min:1|max:100',
        ], doing: 'list people');

        $q = trim((string) $request->query('q'));
        $c = $this->context();

        $people = $c->persons();
        if ($q !== '') {
            $words  = PersonSearch::words($q);
            $people = $people->map(fn($p) => PersonSearch::match($p, $words))->filter();
        }

        $people = $this->resolver()->applyHints($people, [
            'born'       => $request->query('born'),
            'city'       => $request->query('city'),
            'gender'     => $request->query('gender'),
            'related_to' => $request->query('related_to') ? (int) $request->query('related_to') : null,
        ]);
        if ($people === null) {
            return ApiResponse::invalid('related_to must be the id of one of your people.');
        }

        if ($request->query('has_active_prayer_needs') !== null) {
            $wanted = $request->boolean('has_active_prayer_needs');
            $people = $people->filter(fn($p) => ($c->activePrayerNeeds($p->id) > 0) === $wanted);
        }

        $people = $request->query('sort') === 'recent'
            ? $people->sort(fn($a, $b) => strcmp($c->lastEntryDate($b->id) ?? '', $c->lastEntryDate($a->id) ?? '')   // latest contact first
                ?: strcmp(PersonSearch::norm($a->display_name), PersonSearch::norm($b->display_name)))
            : $people->sortBy(fn($p) => PersonSearch::norm($p->display_name));

        [$page, $perPage] = $this->paging($request);
        $total = $people->count();
        $rows  = $people->slice(($page - 1) * $perPage, $perPage)->map(fn($p) => ApiPresenter::summary($p, $c))->values()->all();

        $this->summarise('listed people');

        return ApiResponse::ok(Narrator::people($q, $total), $rows, meta: $this->pagination($total, $page, $perPage));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $include = $this->includes($request);

        return $this->showPerson($this->personById($id), $include);
    }

    /** GET /people/by?name=...: like resolve, then the person. Ambiguous is reported, not guessed. */
    public function by(Request $request): JsonResponse
    {
        $this->input($request, [
            'name' => 'nullable|string|max:200', 'id' => 'nullable|integer', 'born' => 'nullable|string|max:10',
            'city' => 'nullable|string|max:100', 'gender' => 'nullable|in:male,female,unknown', 'include' => 'nullable|string',
        ], doing: 'find that person');

        $ref    = array_filter($request->only(['name', 'id', 'born', 'city', 'gender']), fn($v) => $v !== null && $v !== '');
        $person = $this->person($ref, write: false);

        return $this->showPerson($person, $this->includes($request));
    }

    /** GET /people/{id}/briefing */
    public function briefing(int $id): JsonResponse
    {
        $p = $this->personById($id);
        $c = $this->context();

        $last = (new EntryQuery($this->userId(), $c))->run([
            'person_id' => $p->id, 'types' => ['note', 'prayer_need', 'goal', 'outcome'], 'per_page' => 1,
        ])['entries'][0] ?? null;

        $needs = $p->prayerNeeds()->unresolved()->orderByDesc('significance')->get();
        $goals = $p->goals()->active()->orderBy('target_date')->get();

        $next = $p->keyDates()->get()->filter(fn($k) => $k->days_until !== null)->sortBy('days_until')->first();

        $facts = [
            'display_name'        => $p->display_name,
            'age'                 => $c->age($p->id),
            'relationships'       => $c->relationshipLabels($p->id),
            'cities'              => $c->cities($p->id),
            'died'                => $c->death($p->id),
            'last_entry'          => $last ? ['type_label' => ApiPresenter::typeLabel($last['type']), 'date' => $last['date'], 'title' => $last['title']] : null,
            'active_prayer_needs' => $needs->map(fn($n) => ['title' => $n->title, 'significance' => $n->significance, 'date' => $n->date->toDateString()])->all(),
            'active_goals'        => $goals->map(fn($g) => ['title' => $g->title, 'target_date' => $g->target_date?->toDateString()])->all(),
            'open_tasks'          => $c->openTasks($p->id),
            'next_key_date'       => $next ? ['label' => $next->display_title, 'days_until' => $next->days_until, 'date' => $next->next_occurrence?->toDateString()] : null,
        ];

        $this->summarise('briefed one person');

        return ApiResponse::ok(Narrator::personBriefing(array_filter($facts, fn($v) => $v !== null)), [
            'person' => ApiPresenter::summary($p, $c),
        ] + $facts);
    }

    // -------------------------------------------------------------------------
    // Writes
    // -------------------------------------------------------------------------

    public function store(Request $request): JsonResponse
    {
        $input = $this->input($request, [
            'first_name'                 => 'nullable|string|max:255',
            'last_name'                  => 'nullable|string|max:255',
            'middle_names'               => 'nullable|string|max:255',
            'preferred_name'             => 'nullable|string|max:255',
            'gender'                     => 'nullable|in:male,female,unknown',
            'born'                       => 'nullable|date',
            'born_year_unknown'          => 'boolean',
            'about'                      => 'nullable|string|max:10000',
            'addresses'                  => 'nullable|array|max:10',
            'addresses.*'                => 'array',
            ...$this->addressRules('addresses.*.'),
        ], ['confirm_duplicate'], doing: 'add that person');

        if (empty($input['first_name']) && empty($input['last_name'])) {
            return ApiResponse::fieldError('first_name', 'Please give at least a first or last name.', 'add that person');
        }

        $fullName = trim(implode(' ', array_filter([$input['first_name'] ?? null, $input['last_name'] ?? null])));
        $dupes    = $this->resolver()->exactMatches($fullName)
            ->filter(fn($p) => empty($input['born']) || $this->birthCompatible($p->id, $input['born']))->values();

        if ($dupes->isNotEmpty() && !$request->boolean('confirm_duplicate')) {
            $candidates = $dupes->map(fn($p) => ApiPresenter::summary($p, $this->context()))->all();

            return ApiResponse::make('possible_duplicate',
                Narrator::duplicatePerson($candidates[0]) . (count($candidates) > 1 ? ' (' . (count($candidates) - 1) . ' more like them also exist.)' : ''),
                ['candidates' => $candidates], 409);
        }

        $label = $fullName . (!empty($input['born']) ? ' (' . Narrator::date($input['born'], $input['born_year_unknown'] ?? false) . ')' : '');
        if (ApiResponse::isDryRun()) {
            return ApiResponse::ok("Would add {$label}.", null, $this->warnings());
        }

        $person = DB::transaction(function () use ($input) {
            $person = Person::create(['user_id' => $this->userId(), 'gender' => $input['gender'] ?? null, 'notes' => $input['about'] ?? null]);

            PersonName::create([
                'person_id'      => $person->id, 'type' => 'birth', 'is_primary' => true,
                'first_name'     => $input['first_name'] ?? null, 'last_name' => $input['last_name'] ?? null,
                'middle_names'   => $input['middle_names'] ?? null, 'preferred_name' => $input['preferred_name'] ?? null,
            ]);

            (new SavePersonKeyDate)->execute($person, 'birthday', $input['born'] ?? null, $input['born_year_unknown'] ?? false, $this->provenance());

            foreach ($input['addresses'] ?? [] as $address) {
                $this->createAddress($person, $address);
            }

            return $person;
        });

        $this->summarise('created a person');
        $this->refreshContext(); // the new person must be visible to the presenter

        return ApiResponse::created("Added {$label}.", ApiPresenter::summary($this->personById($person->id), $this->context()), $this->warnings(), [
            ['hint' => 'Add a note about them', 'method' => 'POST', 'path' => '/api/v1/entries'],
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $input  = $this->input($request, [
            'gender'        => 'nullable|in:male,female,unknown',
            'about'         => 'nullable|string|max:10000',
            'date_of_death' => 'nullable|date',
        ], doing: 'update that person');
        $person = $this->personById($id);

        $changed = array_keys($input);
        if (ApiResponse::isDryRun()) {
            return ApiResponse::ok('Would update ' . $this->label($person) . ': ' . (Narrator::join($changed) ?: 'nothing') . '.');
        }

        $fields = [];
        array_key_exists('gender', $input) && $fields['gender'] = $input['gender'];
        array_key_exists('about', $input) && $fields['notes'] = $input['about'];
        $fields && $person->update($fields);

        if (array_key_exists('date_of_death', $input)) {
            (new SavePersonKeyDate)->execute($person, 'death', $input['date_of_death'], false, $this->provenance());
        }

        $this->summarise('updated a person');
        $this->refreshContext();

        return ApiResponse::ok('Updated ' . $this->label($this->personById($id)) . ': ' . (Narrator::join($changed) ?: 'nothing changed') . '.',
            ApiPresenter::summary($this->personById($id), $this->context()), $this->warnings());
    }

    public function addName(Request $request, int $id): JsonResponse
    {
        $input  = $this->input($request, [
            'type'               => 'nullable|in:birth,married,preferred,other',
            'first_name'         => 'nullable|string|max:255',
            'middle_names'       => 'nullable|string|max:255',
            'last_name'          => 'nullable|string|max:255',
            'preferred_name'     => 'nullable|string|max:255',
            'is_primary'         => 'boolean',
            'spelling_uncertain' => 'boolean',
            'notes'              => 'nullable|string|max:500',
        ], doing: 'add that name');
        $person = $this->personById($id);

        if (empty($input['first_name']) && empty($input['last_name'])) {
            return ApiResponse::fieldError('first_name', 'Please give at least a first or last name.', 'add that name');
        }

        $name = trim(implode(' ', array_filter([$input['first_name'] ?? null, $input['middle_names'] ?? null, $input['last_name'] ?? null])));
        if (ApiResponse::isDryRun()) {
            return ApiResponse::ok("Would add the name {$name} for " . $this->label($person) . '.');
        }

        $created = PersonName::create(['person_id' => $person->id, 'type' => 'birth', 'is_primary' => false] + $input);
        if (!empty($input['is_primary'])) {
            $created->setAsPrimary();
        }

        $this->summarise('added a name');
        $this->refreshContext();

        return ApiResponse::created("Added the name {$name} for " . $this->label($this->personById($id)) . '.', ['name_id' => $created->id], $this->warnings());
    }

    public function addAddress(Request $request, int $id): JsonResponse
    {
        $input  = $this->input($request, $this->addressRules(), doing: 'add that address');
        $person = $this->personById($id);

        if (!array_filter(array_diff_key($input, array_flip(['is_current', 'notes'])))) {
            return ApiResponse::fieldError('line_1', 'Please give at least part of an address.', 'add that address');
        }

        if (ApiResponse::isDryRun()) {
            return ApiResponse::ok('Would add an address for ' . $this->label($person) . '.');
        }

        $address = $this->createAddress($person, $input);

        $this->summarise('added an address');
        $this->refreshContext();

        return ApiResponse::created('Added an address for ' . $this->label($this->personById($id)) . '.', ['address_id' => $address->id], $this->warnings());
    }

    public function setBirthday(Request $request, int $id): JsonResponse
    {
        $input  = $this->input($request, ['date' => 'required|date', 'year_unknown' => 'boolean'], doing: 'set that birthday');
        $person = $this->personById($id);

        if (ApiResponse::isDryRun()) {
            return ApiResponse::ok("Would set the birthday of {$person->display_name} to " . Narrator::date($input['date'], $input['year_unknown'] ?? false) . '.');
        }

        (new SavePersonKeyDate)->execute($person, 'birthday', $input['date'], $input['year_unknown'] ?? false, $this->provenance());

        $this->summarise('set a birthday');
        $this->refreshContext();

        return ApiResponse::ok('Set the birthday of ' . $this->label($this->personById($id)) . '.', ApiPresenter::summary($this->personById($id), $this->context()), $this->warnings());
    }

    // -------------------------------------------------------------------------

    private function includes(Request $request): array
    {
        $this->input($request, ['include' => 'nullable|string', 'name' => 'nullable', 'id' => 'nullable', 'born' => 'nullable', 'city' => 'nullable', 'gender' => 'nullable'], doing: 'show that person');

        $raw = trim((string) $request->query('include'));
        if ($raw === '*') {
            return self::INCLUDES;
        }

        $include = array_filter(array_map('trim', explode(',', $raw)));
        if ($bad = array_diff($include, self::INCLUDES)) {
            $this->halt(ApiResponse::invalid('Unknown include: ' . implode(', ', $bad) . '. Use ' . implode(', ', self::INCLUDES) . ' or *.'));
        }

        return $include;
    }

    private function showPerson(Person $p, array $include): JsonResponse
    {
        $c       = $this->context();
        $summary = ApiPresenter::summary($p, $c);
        $data    = ApiPresenter::person($p, $c, $include) + [
            'cities' => $summary['cities'], 'relationship_labels' => $summary['relationships'],
            'last_entry_date' => $summary['last_entry_date'], 'active_prayer_needs_count' => $summary['active_prayer_needs'],
            'open_tasks' => $c->openTasks($p->id),
        ];

        $this->summarise('read one person');

        return ApiResponse::ok(Narrator::personSummary($summary + ['open_tasks' => $c->openTasks($p->id)]), $data, $this->warnings());
    }

    /** A new person's birth date can't contradict an existing one of the same name. */
    private function birthCompatible(int $personId, string $born): bool
    {
        $existing = $this->context()->birthday($personId);
        if (!$existing) {
            return true;
        }

        return $existing['year_unknown']
            ? substr($existing['date'], 5) === substr($born, 5, 5)
            : $existing['date'] === substr($born, 0, 10);
    }

    private function addressRules(string $prefix = ''): array
    {
        $rules = [];
        foreach (['line_1', 'line_2', 'line_3', 'city', 'county', 'postcode', 'country'] as $field) {
            $rules[$prefix . $field] = 'nullable|string|max:255';
        }
        $rules[$prefix . 'is_current'] = 'boolean';
        $rules[$prefix . 'notes']      = 'nullable|string|max:500';

        return $rules;
    }

    private function createAddress(Person $person, array $fields): Address
    {
        $current = $fields['is_current'] ?? true;

        return DB::transaction(function () use ($person, $fields, $current) {
            if ($current) {
                $person->addresses()->where('is_current', true)->update(['is_current' => false]);
            }

            return Address::create($fields + ['person_id' => $person->id, 'is_current' => $current, 'date_added' => $this->today()]);
        });
    }
}
