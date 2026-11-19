<?php

namespace App\Services\Api;

use Carbon\Carbon;

/**
 * The plain-English `message` of every API response, built from templates over plain arrays:
 * deterministic and free of any database or LLM, so it is cheap, reproducible and unit-testable.
 * Uses names, never pronouns inferred from gender.
 *
 * A "person" array here is what ApiPresenter::summary() returns (id, display_name, born (a date
 * string), born_year_unknown, age, cities, relationships, ...); only the keys used are required.
 */
class Narrator
{
    public static function date(?string $iso, bool $yearUnknown = false): string
    {
        if (!$iso) {
            return '';
        }
        return Carbon::parse($iso)->format($yearUnknown ? 'j M' : 'j M Y');
    }

    public static function plural(int $count, string $noun, ?string $plural = null): string
    {
        return $count . ' ' . ($count === 1 ? $noun : ($plural ?? $noun . 's'));
    }

    /** "A", "A and B", "A, B and C" */
    public static function join(array $items, string $conjunction = 'and'): string
    {
        $items = array_values($items);
        if (count($items) <= 1) {
            return $items[0] ?? '';
        }
        return implode(', ', array_slice($items, 0, -1)) . " {$conjunction} " . end($items);
    }

    private static function number(int $n): string
    {
        return [2 => 'two', 3 => 'three', 4 => 'four', 5 => 'five'][$n] ?? (string) $n;
    }

    /** "Al McNicoll (13 Jul 1984)": the born date is what tells two people of one name apart. */
    public static function label(array $person): string
    {
        $born = $person['born'] ?? null;
        return $person['display_name']
            . ($born ? ' (' . self::date($born, $person['born_year_unknown'] ?? false) . ')' : '');
    }

    // -------------------------------------------------------------------------
    // Finding people
    // -------------------------------------------------------------------------

    /** How a distinguishing field reads in "say which one, for example by ...". */
    private const FIELD_PHRASES = [
        'born'          => 'date of birth',
        'cities'        => 'town or city',
        'relationships' => 'who they are related to',
        'gender'        => 'gender',
    ];

    private static function fieldValues(array $candidates, string $field): array
    {
        return collect($candidates)->map(function ($c) use ($field) {
            $value = $c[$field] ?? null;
            return match ($field) {
                'born'    => $value ? self::date($value, $c['born_year_unknown'] ?? false) : null,
                'cities', 'relationships' => $value ? implode(' / ', $value) : null,
                default   => $value,
            };
        })->filter()->unique()->values()->all();
    }

    /** @param array[] $candidates person arrays */
    public static function ambiguous(string $reference, array $candidates, array $distinguishing, bool $write, int $total): string
    {
        $count = $total ?: count($candidates);
        $out   = "There " . ($count === 2 ? 'are two people' : "are " . self::number($count) . ' people')
               . " called {$reference} in PastorEyes, so " . ($write ? "I haven't saved anything." : "I haven't picked one.");

        if ($distinguishing) {
            $field   = $distinguishing[0];
            $values  = self::fieldValues($candidates, $field);
            $example = $values ? ' (' . self::join($values, 'or') . ')' : '';
            $out    .= ' Please say which one, for example by ' . self::FIELD_PHRASES[$field] . $example . ', or by passing their id.';
        } else {
            $out .= " I can't tell them apart from what is recorded, so please ask the user to add a distinguishing detail"
                  . ' (a birth date, address or relationship) in PastorEyes, or use their ids.';
        }

        if ($total > count($candidates)) {
            $out .= ' Showing the first ' . count($candidates) . " of {$total}.";
        }

        return $out;
    }

    /** @param string[] $suggestions display names */
    public static function notFound(string $reference, array $suggestions): string
    {
        $out = "I couldn't find anyone called '{$reference}'.";

        if ($suggestions) {
            $out .= ' The closest ' . (count($suggestions) === 1 ? 'name is ' : 'names are ')
                  . self::join($suggestions, 'and') . '. Did you mean ' . (count($suggestions) === 1 ? 'them' : 'one of them') . '?';
        }

        return $out;
    }

    /**
     * Several people referred to in one call and at least one is unusable (nothing is written).
     *
     * @param array[] $items each: reference, status (ok|ambiguous|not_found), person?, candidates?, suggestions?
     */
    public static function problems(array $items, bool $write): string
    {
        $parts = [];
        $bad   = 0;
        foreach ($items as $item) {
            $ref = $item['reference'];
            if ($item['status'] === 'ok') {
                $parts[] = self::label($item['person']) . ' was found';
            } elseif ($item['status'] === 'ambiguous') {
                $bad++;
                $values  = self::fieldValues($item['candidates'], ($item['distinguishing'][0] ?? 'born'));
                $parts[] = "{$ref} matches " . self::number(count($item['candidates'])) . ' people'
                         . ($values ? ' (' . self::join($values, 'or') . ')' : '');
            } else {
                $bad++;
                $names   = array_column($item['suggestions'] ?? [], 'display_name');
                $parts[] = "I couldn't find anyone called {$ref}" . ($names ? ' (did you mean ' . self::join($names, 'or') . '?)' : '');
            }
        }

        return ($write ? "I haven't saved anything." : 'I could not resolve every person.')
            . ' Of the ' . self::number(count($items)) . ' people: ' . implode('; ', $parts) . '.'
            . ' Please correct ' . ($bad === 1 ? 'that one' : ($bad === 2 ? 'those two' : 'those')) . ' and resend.';
    }

    /** Hints (born, city...) ruled out every person the name matched. */
    public static function hintsExcluded(string $reference, int $matched): string
    {
        return "I found " . self::plural($matched, 'person', 'people') . " called {$reference}, but none match the details given (born, city, gender or related_to)."
             . ' Check them, or resolve the name on its own to see who is there.';
    }

    public static function duplicatePerson(array $existing): string
    {
        return 'Someone called ' . self::label($existing) . ' already exists (id ' . $existing['id'] . '). '
             . 'If this really is a different person, resend with confirm_duplicate: true.';
    }

    // -------------------------------------------------------------------------
    // Describing people
    // -------------------------------------------------------------------------

    public static function personSummary(array $p): string
    {
        $out = $p['display_name'];

        $bits = [];
        if (!empty($p['born'])) {
            $bits[] = 'born ' . self::date($p['born'], $p['born_year_unknown'] ?? false)
                    . (isset($p['age']) ? ', aged ' . $p['age'] : '');
        }
        if ($bits) {
            $out .= ' (' . implode(', ', $bits) . ')';
        }

        if (!empty($p['relationships'])) {
            $out .= ', ' . self::join(array_slice($p['relationships'], 0, 3));
        }
        $out .= '. ' . self::plural($p['active_prayer_needs'] ?? 0, 'active prayer need')
              . ', ' . self::plural($p['open_tasks'] ?? 0, 'open task') . '.';

        $out .= !empty($p['last_entry_date'])
            ? ' Last entry: ' . self::date($p['last_entry_date']) . '.'
            : ' No entries yet.';

        return $out;
    }

    /**
     * "Tell me about X before I visit." $p carries the pieces of ApiPresenter::personBriefing().
     */
    public static function personBriefing(array $p): string
    {
        $name  = $p['display_name'];
        $lead  = $name . (isset($p['age']) ? ', ' . $p['age'] : '');
        if (!empty($p['relationships'])) {
            $lead .= ', ' . self::join(array_slice($p['relationships'], 0, 3));
        }
        if (!empty($p['cities'])) {
            $lead .= ', lives in ' . $p['cities'][0];
        }
        $sentences = [$lead . '.'];

        if (!empty($p['died'])) {
            $sentences[] = 'Died ' . self::date($p['died']['date'], $p['died']['year_unknown'] ?? false) . '.';
        }

        $last = $p['last_entry'] ?? null;
        $sentences[] = $last
            ? 'Last contact was a ' . strtolower($last['type_label']) . ' on ' . self::date($last['date'])
                . (!empty($last['title']) ? " ('{$last['title']}')" : '') . '.'
            : 'There are no entries about ' . $name . ' yet.';

        $needs = $p['active_prayer_needs'] ?? [];
        if ($needs) {
            $items = array_map(fn($n) => ($n['title'] ?: 'untitled') . ' (significance ' . $n['significance'] . ', since ' . self::date($n['date']) . ')', $needs);
            $sentences[] = self::plural(count($needs), 'active prayer need') . ': ' . self::join($items) . '.';
        } else {
            $sentences[] = 'No active prayer needs.';
        }

        $goals = $p['active_goals'] ?? [];
        if ($goals) {
            $items = array_map(fn($g) => "'{$g['title']}'" . ($g['target_date'] ? ' (target ' . self::date($g['target_date']) . ')' : ''), $goals);
            $sentences[] = self::plural(count($goals), 'goal') . ' in progress: ' . self::join($items) . '.';
        }

        if (!empty($p['open_tasks'])) {
            $sentences[] = self::plural($p['open_tasks'], 'open task') . '.';
        }

        if (isset($p['next_key_date'])) {
            $k = $p['next_key_date'];
            $sentences[] = $k['label'] . ' ' . ($k['days_until'] === 0 ? 'is today' : 'in ' . self::plural($k['days_until'], 'day')) . '.';
        }

        return implode(' ', $sentences);
    }

    public static function people(string $query, int $total): string
    {
        $scope = $query === '' ? '' : " matching '{$query}'";
        return $total === 0 ? "No people found{$scope}." : 'Found ' . self::plural($total, 'person', 'people') . $scope . '.';
    }

    // -------------------------------------------------------------------------
    // Briefing, entries and tasks
    // -------------------------------------------------------------------------

    /** @param array<string,int> $counts keys: key_dates, prayer_needs, tasks, goals, sync_differences */
    public static function briefing(array $counts, int $days): string
    {
        $parts = [
            self::plural($counts['key_dates'], 'key date') . " in the next {$days} days",
            self::plural($counts['prayer_needs'], 'active prayer need'),
            self::plural($counts['tasks'], 'task') . ' due or overdue',
            self::plural($counts['goals'], 'goal') . ' with an approaching target date',
        ];
        $out = 'Needing attention: ' . self::join($parts) . '.';

        if (($counts['sync_differences'] ?? 0) > 0) {
            $out .= ' ' . self::plural($counts['sync_differences'], 'contact sync difference') . ' waiting for review in the app.';
        }

        return $out;
    }

    public static function entries(int $total, int $shown, array $filters): string
    {
        $what = $filters['type_label'] ?? 'entries';
        $out  = $total === 0 ? "No {$what} found" : "Found {$total} {$what}";
        if (!empty($filters['person'])) {
            $out .= ' for ' . $filters['person'];
        }
        $out .= '.';

        if ($shown < $total) {
            $out .= " Showing {$shown}; use page to see more.";
        }

        return $out;
    }

    public static function tasks(int $total, string $status): string
    {
        $word = ['active' => 'active', 'completed' => 'completed', 'all' => ''][$status] ?? '';
        return $total === 0
            ? 'No ' . trim($word . ' tasks') . ' found.'
            : 'Found ' . self::plural($total, trim($word . ' task')) . '.';
    }

    /** "Added a Note 'Hospital visit' dated 18 Nov 2026 for Al McNicoll (13 Jul 1984) and Caroline McNicoll." */
    public static function entryAdded(string $typeLabel, ?string $title, string $date, array $people, bool $dryRun): string
    {
        $article = in_array(strtolower($typeLabel[0]), ['a', 'e', 'i', 'o', 'u'], true) ? 'an' : 'a';
        $verb    = $dryRun ? "Would add {$article}" : "Added {$article}";

        return "{$verb} {$typeLabel}" . ($title ? " '{$title}'" : '') . " dated {$date} for "
            . self::join(array_map([self::class, 'label'], $people)) . '.';
    }
}
