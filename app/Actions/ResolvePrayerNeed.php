<?php

namespace App\Actions;

use App\Models\PrayerNeed;

class ResolvePrayerNeed
{
    public function resolve(PrayerNeed $need, ?string $resolvedAt = null, ?string $details = null): PrayerNeed
    {
        $need->resolved_at        = $resolvedAt ?: now()->toDateString();
        $need->resolution_details = $details ?: null;
        $need->save();

        return $need;
    }

    /** Make it active again: clears the date and the details. */
    public function reopen(PrayerNeed $need): PrayerNeed
    {
        $need->resolved_at        = null;
        $need->resolution_details = null;
        $need->save();

        return $need;
    }
}
