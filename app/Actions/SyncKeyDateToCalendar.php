<?php

namespace App\Actions;

use App\Models\KeyDate;
use App\Services\Google\GoogleCalendarService;

class SyncKeyDateToCalendar
{
    /**
     * Push a KeyDate to Google Calendar, creating or updating as appropriate.
     * Updates the KeyDate record with the Google event ID and calendar ID.
     */
    public function execute(KeyDate $keyDate, ?string $calendarId = null): bool
    {
        $user    = auth()->user();
        $service = new GoogleCalendarService($user);

        // Use provided calendar, or existing one on the record, or user's default
        $calendarId = $calendarId
            ?? $keyDate->google_calendar_id
            ?? $user->settings['default_calendar_id']
            ?? 'primary';

        // Load persons for event description
        $keyDate->load('persons.primaryName');

        if ($keyDate->google_calendar_event_id) {
            // Update existing event
            $success = $service->updateEvent($keyDate);

            if ($success && $calendarId !== $keyDate->google_calendar_id) {
                // Calendar changed — delete old event and create new one
                $service->deleteEvent($keyDate);
                $eventId = $service->createEvent($keyDate, $calendarId);
                if ($eventId) {
                    $keyDate->google_calendar_event_id = $eventId;
                    $keyDate->google_calendar_id       = $calendarId;
                    $keyDate->save();
                    return true;
                }
                return false;
            }

            return $success;
        } else {
            // Create new event
            $eventId = $service->createEvent($keyDate, $calendarId);

            if ($eventId) {
                $keyDate->google_calendar_event_id = $eventId;
                $keyDate->google_calendar_id       = $calendarId;
                $keyDate->save();
                return true;
            }

            return false;
        }
    }
}
