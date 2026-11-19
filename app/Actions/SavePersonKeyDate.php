<?php

namespace App\Actions;

use App\Models\KeyDate;
use App\Models\Person;

/**
 * A person's birthday and date of death are Key Dates, not person fields. This sets, replaces or
 * (with a null date) removes the one of a given type, as the Overview tab and the API both need.
 */
class SavePersonKeyDate
{
    /** What a newly created key date of each type starts with. A Death is a memorial for its first 3 anniversaries. */
    private const DEFAULTS = [
        'birthday' => ['is_recurring' => true, 'recurrence_count' => null, 'significance' => 3],
        'death'    => ['is_recurring' => true, 'recurrence_count' => 3,    'significance' => 4],
    ];

    public function execute(Person $person, string $type, ?string $date, bool $yearUnknown = false, array $source = []): ?KeyDate
    {
        $existing = $person->keyDates()->where('type', $type)->first();

        if (!$date) {
            $existing?->delete();
            return null;
        }

        $data = ['date' => $date, 'year_unknown' => $yearUnknown, 'logged_at' => now()];

        if ($existing) {
            $existing->update($data);
            return $existing;
        }

        $keyDate = KeyDate::create($data + self::DEFAULTS[$type] + $source + ['user_id' => $person->user_id, 'type' => $type]);
        $keyDate->persons()->attach($person->id, ['is_primary' => true]);

        return $keyDate;
    }
}
