<?php

namespace App\Services\Api;

use App\Models\Person;
use App\Services\PersonSearch;
use Illuminate\Support\Collection;

/**
 * Turns a PersonRef (an id, a name, or a name plus hints) into exactly one of the user's people,
 * or says plainly why it can't. Writes never guess: several matches is `ambiguous`, not "the first".
 *
 * Matching: every word of the name must appear somewhere in the person's names (any name they have,
 * any order, case and accents ignored). If some candidates match a full name exactly and others only
 * in part, the exact ones win. Hints (born, city, gender, related_to) are then applied as filters.
 */
class PersonResolver
{
    public const MAX_CANDIDATES = 10;
    private const HINTS = ['name', 'id', 'born', 'city', 'gender', 'related_to'];

    public function __construct(public readonly PersonContext $context) {}

    public static function for(int $userId, ?PersonContext $context = null): self
    {
        return new self($context ?? new PersonContext($userId));
    }

    /**
     * @param  mixed  $ref  42 | "Al McNicoll" | { name, born?, city?, gender?, related_to?, id? }
     * @return array{status: string, reference: string, person?: Person, candidates?: Collection,
     *               total?: int, also_matched?: Collection, suggestions?: Collection, message?: string}
     *         status: ok | ambiguous | not_found | invalid
     */
    public function resolve(mixed $ref): array
    {
        $ref = $this->normalise($ref);
        if (is_string($ref)) {
            return ['status' => 'invalid', 'reference' => '', 'message' => $ref];
        }

        // An id overrides everything else
        if ($ref['id'] !== null) {
            $person = $this->context->person((int) $ref['id']);
            return $person
                ? ['status' => 'ok', 'reference' => "person {$ref['id']}", 'person' => $person]
                : ['status' => 'not_found', 'reference' => "person {$ref['id']}", 'suggestions' => collect()];
        }

        $reference = $ref['name'];
        $terms     = PersonSearch::words($reference);

        $matches = $this->context->persons()->filter(fn(Person $p) => PersonSearch::match($p, $terms));
        if ($matches->isEmpty()) {
            return ['status' => 'not_found', 'reference' => $reference, 'suggestions' => $this->suggestions($terms)];
        }

        // Exact full-name matches beat partial ones ("Al" also matches Alan Smith)
        $alsoMatched = collect();
        $exact = $matches->filter(fn(Person $p) => $this->matchesExactly($p, $reference));
        if ($exact->isNotEmpty() && $exact->count() < $matches->count()) {
            $alsoMatched = $matches->diffKeys($exact);
            $matches     = $exact;
        }

        $before  = $matches->count();
        $matches = $this->applyHints($matches, $ref);
        if ($matches === null) {
            return ['status' => 'invalid', 'reference' => $reference,
                    'message' => "I couldn't use the related_to hint for {$reference}: it must name one person (by id, or by a name that matches only one person)."];
        }

        if ($matches->isEmpty()) {
            return ['status' => 'not_found', 'reference' => $reference, 'suggestions' => collect(),
                    'excluded_by_hints' => $before, 'also_matched' => $alsoMatched];
        }

        if ($matches->count() === 1) {
            $person = $matches->first();

            // A lone partial match ("Pat Quill" for Pat Quillen) is used, but flagged so it is checked
            return ['status' => 'ok', 'reference' => $reference, 'person' => $person, 'also_matched' => $alsoMatched,
                    'partial' => !$this->matchesExactly($person, $reference)];
        }

        $sorted = $matches->sortBy(fn(Person $p) => PersonSearch::norm($p->display_name))->values();

        return [
            'status'       => 'ambiguous',
            'reference'    => $reference,
            'candidates'   => $sorted->take(self::MAX_CANDIDATES),
            'total'        => $sorted->count(),
            'also_matched' => $alsoMatched,
        ];
    }

    /**
     * Resolve every reference before anything is written. Returns the people in order, or the
     * problems (one entry per reference) when any is ambiguous or missing.
     *
     * @return array{ok: bool, people: Person[], results: array[]}
     */
    public function resolveMany(array $refs): array
    {
        $results = array_map(fn($ref) => $this->resolve($ref), array_values($refs));
        $ok      = collect($results)->every(fn($r) => $r['status'] === 'ok');

        // The same person named twice counts once
        $people = collect($results)->pluck('person')->filter()->unique('id')->values()->all();

        return ['ok' => $ok, 'people' => $ok ? $people : [], 'results' => $results];
    }

    // -------------------------------------------------------------------------

    /** @return array|string a clean ref, or an explanation of what is wrong with it */
    private function normalise(mixed $ref): array|string
    {
        if (is_int($ref) || (is_string($ref) && ctype_digit($ref))) {
            return ['id' => (int) $ref, 'name' => ''] + array_fill_keys(self::HINTS, null);
        }
        if (is_string($ref)) {
            $ref = ['name' => $ref];
        }
        if (!is_array($ref)) {
            return 'A person must be given as an id, a name, or an object with a name.';
        }
        if ($unknown = array_diff(array_keys($ref), self::HINTS)) {
            return 'Unknown person field(s): ' . implode(', ', $unknown) . '. Use ' . implode(', ', self::HINTS) . '.';
        }

        $ref += array_fill_keys(self::HINTS, null);
        $ref['name'] = trim((string) $ref['name']);

        if ($ref['id'] === null && $ref['name'] === '') {
            return 'A person needs a name or an id.';
        }

        return $ref;
    }

    /** People with a full name equal to this one (first + last, with or without middle, or preferred + last). */
    public function exactMatches(string $name): Collection
    {
        return $this->context->persons()->filter(fn(Person $p) => $this->matchesExactly($p, $name))->values();
    }

    private function matchesExactly(Person $person, string $reference): bool
    {
        $wanted = implode(' ', PersonSearch::words($reference));

        $full = [PersonSearch::norm(preg_replace('/\s*\(\?\)$/', '', $person->display_name))];
        foreach ($person->names as $n) {
            $full[] = PersonSearch::norm(implode(' ', array_filter([$n->first_name, $n->middle_names, $n->last_name])));
            $full[] = PersonSearch::norm(implode(' ', array_filter([$n->first_name, $n->last_name])));
            $full[] = PersonSearch::norm(implode(' ', array_filter([$n->preferred_name, $n->last_name])));
        }

        return in_array($wanted, array_map(fn($f) => implode(' ', PersonSearch::words($f)), $full), true);
    }

    /**
     * Keep the people matching the hints (born, city, gender, related_to). Null when a related_to
     * hint can't be understood.
     */
    public function applyHints(Collection $people, array $ref): ?Collection
    {
        if ($ref['born'] !== null && $ref['born'] !== '') {
            $people = $people->filter(fn(Person $p) => $this->bornMatches($p->id, (string) $ref['born']));
        }
        if ($ref['gender']) {
            $people = $people->filter(fn(Person $p) => ($p->gender ?? 'unknown') === $ref['gender']);
        }
        if ($ref['city']) {
            $people = $people->filter(fn(Person $p) => $this->cityMatches($p->id, (string) $ref['city']));
        }
        if ($ref['related_to']) {
            $people = $this->relatedTo($people, $ref['related_to']);
        }

        return $people;
    }

    /** born: "1984-07-13", "1984" (year only) or "07-13" (day and month only). Unknown years match day and month. */
    private function bornMatches(int $personId, string $born): bool
    {
        $birthday = $this->context->birthday($personId);
        if (!$birthday) {
            return false;
        }

        $date = $birthday['date'];
        return match (true) {
            (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $born) => $birthday['year_unknown']
                ? substr($date, 5) === substr($born, 5)
                : $date === $born,
            (bool) preg_match('/^\d{4}$/', $born)             => !$birthday['year_unknown'] && substr($date, 0, 4) === $born,
            (bool) preg_match('/^\d{2}-\d{2}$/', $born)       => substr($date, 5) === $born,
            default                                           => false,
        };
    }

    /** Matches a current or past address: city or county exactly, or a postcode prefix. */
    private function cityMatches(int $personId, string $city): bool
    {
        $wanted   = PersonSearch::norm($city);
        $postcode = str_replace(' ', '', $wanted);

        return $this->context->addresses($personId)->contains(function ($a) use ($wanted, $postcode) {
            return PersonSearch::norm($a->city) === $wanted
                || PersonSearch::norm($a->county) === $wanted
                || ($postcode !== '' && str_starts_with(str_replace(' ', '', PersonSearch::norm($a->postcode)), $postcode));
        });
    }

    /** related_to: { person: PersonRef, type?: "spouse" } or a bare PersonRef. */
    private function relatedTo(Collection $people, mixed $hint): ?Collection
    {
        $type   = is_array($hint) && isset($hint['person']) ? ($hint['type'] ?? null) : null;
        $target = is_array($hint) && isset($hint['person']) ? $hint['person'] : $hint;

        // The target must itself resolve to one person (without its own related_to, to stop loops)
        if (is_array($target)) {
            unset($target['related_to']);
        }
        $resolved = $this->resolve($target);
        if ($resolved['status'] !== 'ok') {
            return null;
        }

        $targetId = $resolved['person']->id;
        $wanted   = $type ? PersonSearch::norm(preg_replace('/\s+of$/i', '', $type)) : null;

        return $people->filter(function (Person $p) use ($targetId, $wanted) {
            return $this->context->relationships($p->id)->contains(function ($r) use ($p, $targetId, $wanted) {
                $other = $r->person_id === $p->id ? $r->related_person_id : $r->person_id;
                if ($other !== $targetId) {
                    return false;
                }
                return $wanted === null
                    || PersonSearch::norm(preg_replace('/\s+of$/i', '', $r->labelForPerson($p->id))) === $wanted;
            });
        });
    }

    /** Names within a couple of typos of every word typed: advisory "did you mean". */
    private function suggestions(array $terms): Collection
    {
        return $this->context->persons()
            ->map(function (Person $p) use ($terms) {
                $words = collect($p->names)->flatMap(fn($n) => PersonSearch::words(
                    implode(' ', [$n->first_name, $n->middle_names, $n->last_name, $n->preferred_name])
                ))->push(...PersonSearch::words($p->display_name))->unique();

                $total = 0;
                foreach ($terms as $term) {
                    $allowed = min(2, intdiv(strlen($term), 3));
                    $best    = $words->map(fn($w) => levenshtein($term, $w))->min();
                    if ($best === null || $best > $allowed) {
                        return null;
                    }
                    $total += $best;
                }

                $p->suggestion_distance = $total;
                return $p;
            })
            ->filter()
            ->sortBy('suggestion_distance')
            ->take(5)
            ->values();
    }
}
