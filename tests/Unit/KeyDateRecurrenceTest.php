<?php

namespace Tests\Unit;

use App\Models\KeyDate;
use Carbon\Carbon;
use Tests\TestCase;

class KeyDateRecurrenceTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function death(string $date, ?int $count = 3): KeyDate
    {
        return (new KeyDate)->forceFill([
            'date' => $date, 'is_recurring' => true, 'recurrence_count' => $count, 'year_unknown' => false,
        ]);
    }

    public function test_limited_run_recurs_for_its_anniversaries_then_stops(): void
    {
        $kd = $this->death('2023-06-10');

        Carbon::setTestNow('2026-01-01');                      // before the 3rd anniversary (2026)
        $this->assertSame('2026-06-10', $kd->next_occurrence->toDateString());

        Carbon::setTestNow('2026-07-01');                      // 3rd anniversary has passed
        $this->assertNull($kd->next_occurrence);
    }

    public function test_unlimited_recurrence_never_ends(): void
    {
        Carbon::setTestNow('2040-01-01');

        $this->assertSame('2040-06-10', $this->death('2023-06-10', null)->next_occurrence->toDateString());
    }
}
