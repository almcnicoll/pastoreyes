<?php

namespace App\Services;

use App\Models\Person;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class PrayerEmailScheduler
{
    public const DAYS = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];

    /** How long after the scheduled time an email may still go out (so enabling late doesn't fire a stale one). */
    public const WINDOW_HOURS = 2;

    /**
     * Spread people evenly over the selected days, moving as few people as possible.
     *
     * @param array<int,int> $current   personId => day, as currently stored
     * @param int[]          $personIds people who have outstanding prayer requests
     * @param int[]          $days      selected ISO weekdays
     * @return array<int,int>           personId => day
     */
    public static function balance(array $current, array $personIds, array $days): array
    {
        $days      = array_values(array_unique($days));
        $personIds = array_values(array_unique($personIds));
        sort($days);
        sort($personIds);

        if (!$days || !$personIds) {
            return [];
        }

        // Keep every assignment that is still valid
        $byDay      = array_fill_keys($days, []);
        $unassigned = [];
        foreach ($personIds as $id) {
            $day = $current[$id] ?? null;
            if ($day !== null && isset($byDay[$day])) {
                $byDay[$day][] = $id;
            } else {
                $unassigned[] = $id;
            }
        }

        // n people over d days: everyone gets floor(n/d), and n mod d days get one more.
        // The days that already hold the most people take the extra, to minimise moves.
        $base  = intdiv(count($personIds), count($days));
        $extra = count($personIds) % count($days);
        $order = $days;
        usort($order, fn($a, $b) => count($byDay[$b]) <=> count($byDay[$a]) ?: $a <=> $b);
        $cap = [];
        foreach ($order as $i => $day) {
            $cap[$day] = $base + ($i < $extra ? 1 : 0);
        }

        // Shed surplus from over-full days, then place everyone unassigned where there's room
        foreach ($days as $day) {
            while (count($byDay[$day]) > $cap[$day]) {
                $unassigned[] = array_pop($byDay[$day]);
            }
        }
        sort($unassigned);
        foreach ($unassigned as $id) {
            $target = null;
            foreach ($days as $day) {
                if ($target === null || ($cap[$day] - count($byDay[$day])) > ($cap[$target] - count($byDay[$target]))) {
                    $target = $day;
                }
            }
            $byDay[$target][] = $id;
        }

        $result = [];
        foreach ($byDay as $day => $ids) {
            foreach ($ids as $id) {
                $result[$id] = $day;
            }
        }

        return $result;
    }

    /**
     * If this user's email is due at $now, return today's ISO weekday, otherwise null.
     * $config is users.settings['prayer_email'].
     */
    public static function dueDay(array $config, CarbonInterface $now): ?int
    {
        if (empty($config['enabled'])) {
            return null;
        }

        $local = $now->copy()->setTimezone($config['timezone'] ?? 'Europe/London');
        $day   = $local->isoWeekday();

        if (!in_array($day, $config['days'] ?? [])) {
            return null;
        }

        $time = ($config['same_time'] ?? true)
            ? ($config['time'] ?? '09:00')
            : ($config['times'][$day] ?? $config['time'] ?? '09:00');

        $scheduled = $local->copy()->setTimeFromTimeString($time);
        if ($local->lt($scheduled) || $local->gt($scheduled->copy()->addHours(self::WINDOW_HOURS))) {
            return null;
        }

        if (($config['last_sent_on'] ?? null) === $local->toDateString()) {
            return null;
        }

        return $day;
    }

    /**
     * Re-evaluate the stored day assignments for a user against their selected days
     * and their people with outstanding prayer requests.
     */
    public function rebalance(User $user): void
    {
        $days = $user->settings['prayer_email']['days'] ?? [];

        $personIds = Person::where('user_id', $user->id)
            ->whereHas('prayerNeeds', fn($q) => $q->unresolved())
            ->pluck('id')->all();

        $current = DB::table('prayer_email_assignments')
            ->where('user_id', $user->id)->pluck('day', 'person_id')->all();

        $new = self::balance($current, $personIds, $days);

        DB::transaction(function () use ($user, $current, $new) {
            $removed = array_diff(array_keys($current), array_keys($new));
            if ($removed) {
                DB::table('prayer_email_assignments')
                    ->where('user_id', $user->id)->whereIn('person_id', $removed)->delete();
            }

            foreach ($new as $personId => $day) {
                if (($current[$personId] ?? null) !== $day) {
                    DB::table('prayer_email_assignments')->updateOrInsert(
                        ['user_id' => $user->id, 'person_id' => $personId],
                        ['day' => $day, 'updated_at' => now(), 'created_at' => now()],
                    );
                }
            }
        });
    }

    /** People assigned to $day, with their outstanding prayer request count. */
    public function peopleForDay(User $user, int $day)
    {
        $ids = DB::table('prayer_email_assignments')
            ->where('user_id', $user->id)->where('day', $day)->pluck('person_id');

        return Person::whereIn('id', $ids)
            ->with('primaryName')
            ->withCount(['prayerNeeds as outstanding' => fn($q) => $q->unresolved()])
            ->get()
            ->sortBy(fn($p) => strtolower($p->display_name))
            ->values();
    }
}
