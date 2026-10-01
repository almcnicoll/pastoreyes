<?php

namespace Tests\Unit;

use App\Services\PrayerEmailScheduler as S;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class PrayerEmailSchedulerTest extends TestCase
{
    private function perDay(array $assignment): array
    {
        $counts = array_count_values($assignment);
        ksort($counts);
        return $counts;
    }

    public function test_eight_people_over_six_days_is_two_twos_and_four_ones(): void
    {
        $result = S::balance([], range(1, 8), [1, 2, 3, 4, 5, 6]);

        $this->assertCount(8, $result);
        $counts = array_values($this->perDay($result));
        sort($counts);
        $this->assertSame([1, 1, 1, 1, 2, 2], $counts);
    }

    public function test_adding_a_person_moves_nobody(): void
    {
        $before = S::balance([], range(1, 8), [1, 2, 3, 4, 5, 6]);
        $after  = S::balance($before, range(1, 9), [1, 2, 3, 4, 5, 6]);

        foreach ($before as $id => $day) {
            $this->assertSame($day, $after[$id]);
        }
    }

    public function test_resolving_a_request_moves_only_as_many_as_needed(): void
    {
        // 6 people over 3 days = 2 each; person 6 resolves, leaving 5 over 3 days (2,2,1)
        $before = S::balance([], range(1, 6), [1, 2, 3]);
        $after  = S::balance($before, range(1, 5), [1, 2, 3]);

        $moved = count(array_filter($after, fn($day, $id) => $before[$id] !== $day, ARRAY_FILTER_USE_BOTH));
        $this->assertSame(0, $moved);
    }

    public function test_unbalanced_assignment_moves_the_minimum(): void
    {
        // 4 people all on day 1, days 1 and 2 selected: exactly 2 must move
        $after = S::balance([1 => 1, 2 => 1, 3 => 1, 4 => 1], [1, 2, 3, 4], [1, 2]);

        $this->assertSame([1 => 2, 2 => 2], $this->perDay($after));
        $this->assertCount(2, array_filter($after, fn($d) => $d === 2));
        $moved = count(array_filter($after, fn($d) => $d !== 1));
        $this->assertSame(2, $moved);
    }

    public function test_deselected_day_reassigns_its_people(): void
    {
        $before = S::balance([], range(1, 6), [1, 2, 3]);
        $after  = S::balance($before, range(1, 6), [1, 2]);

        $this->assertSame([1 => 3, 2 => 3], $this->perDay($after));
    }

    public function test_no_days_or_no_people_gives_nothing(): void
    {
        $this->assertSame([], S::balance([], [1, 2], []));
        $this->assertSame([], S::balance([1 => 1], [], [1, 2]));
    }

    public function test_due_day_respects_day_time_window_and_last_sent(): void
    {
        // Wed 2026-10-07, 09:30 in London (BST, 08:30 UTC)
        $now = Carbon::parse('2026-10-07 08:30:00', 'UTC');
        $cfg = ['enabled' => true, 'timezone' => 'Europe/London', 'days' => [3], 'same_time' => true, 'time' => '09:00'];

        $this->assertSame(3, S::dueDay($cfg, $now));
        $this->assertNull(S::dueDay(['enabled' => false] + $cfg, $now));
        $this->assertNull(S::dueDay(['days' => [2]] + $cfg, $now));                       // not a selected day
        $this->assertNull(S::dueDay(['time' => '09:45'] + $cfg, $now));                   // not time yet
        $this->assertNull(S::dueDay(['time' => '06:00'] + $cfg, $now));                   // outside the window
        $this->assertNull(S::dueDay(['last_sent_on' => '2026-10-07'] + $cfg, $now));      // already sent today
        $this->assertSame(3, S::dueDay(['same_time' => false, 'times' => [3 => '09:15']] + $cfg, $now));
    }
}
