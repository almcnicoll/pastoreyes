<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Collection;

/**
 * Names and active-prayer-need counts only — no prayer need details are sent.
 */
class PrayerReminder extends Mailable
{
    /** @param Collection $people Person models with an `outstanding` count */
    public function __construct(public Collection $people)
    {
        $this->subject('Prayer needs to remember (' . $people->count() . ' ' . ($people->count() === 1 ? 'person' : 'people') . ')');
    }

    public function build(): static
    {
        return $this->view('emails.prayer-reminder');
    }
}
