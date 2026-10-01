<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Collection;

/**
 * Names and outstanding-request counts only — no prayer request details are sent.
 */
class PrayerReminder extends Mailable
{
    /** @param Collection $people Person models with an `outstanding` count */
    public function __construct(public Collection $people)
    {
        $this->subject('Prayer requests to remember (' . $people->count() . ' ' . ($people->count() === 1 ? 'person' : 'people') . ')');
    }

    public function build(): static
    {
        return $this->view('emails.prayer-reminder');
    }
}
