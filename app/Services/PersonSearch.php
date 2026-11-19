<?php

namespace App\Services;

use App\Models\Person;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Finds a user's people by name. Names are encrypted, so SQL can't filter: this loads the
 * user's people and matches in PHP, against every name a person has (middle, preferred,
 * maiden/alternate...). Every word typed must appear somewhere in the person's names.
 * Case and accents are ignored. The API's PersonResolver builds on the same rules.
 */
class PersonSearch
{
    /**
     * @param  int[]  $excludeIds
     * @return Collection<int, Person>  each with a `matched_name` attribute: the other name
     *                                  that matched, or null when the display name matched
     */
    public static function find(int $userId, string $term, array $excludeIds = [], int $limit = 8): Collection
    {
        $terms = self::words($term);
        if (!$terms) {
            return collect();
        }

        return Person::where('user_id', $userId)
            ->when($excludeIds, fn($q) => $q->whereNotIn('id', $excludeIds))
            ->with(['primaryName', 'names'])
            ->get()
            ->map(fn(Person $person) => self::match($person, $terms))
            ->filter()
            ->sortBy(fn($p) => self::norm($p->display_name))
            ->take($limit)
            ->values();
    }

    /** The person, with `matched_name` set, if every word matches somewhere in their names; else null. */
    public static function match(Person $person, array $terms): ?Person
    {
        $display   = self::norm($person->display_name);
        $nameTexts = $person->names->mapWithKeys(fn($n) => [
            trim(implode(' ', array_filter([$n->first_name, $n->middle_names, $n->last_name]))) =>
                self::norm(implode(' ', [$n->first_name, $n->middle_names, $n->last_name, $n->preferred_name])),
        ]);

        if (!self::hasAll($display . ' ' . $nameTexts->implode(' '), $terms)) {
            return null;
        }

        // If the display name alone doesn't explain the match, say which name did
        $person->matched_name = self::hasAll($display, $terms)
            ? null
            : $nameTexts->filter(fn($text) => self::hasAll($text, $terms))->keys()->first();

        return $person;
    }

    /** Lower-case, accent-free, trimmed. */
    public static function norm(?string $text): string
    {
        return mb_strtolower(Str::ascii(trim((string) $text)));
    }

    /** @return string[] */
    public static function words(string $term): array
    {
        return preg_split('/\s+/', self::norm($term), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    public static function hasAll(string $haystack, array $terms): bool
    {
        foreach ($terms as $term) {
            if (!str_contains($haystack, $term)) {
                return false;
            }
        }
        return true;
    }
}
