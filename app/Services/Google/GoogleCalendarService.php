<?php

namespace App\Services\Google;

use App\Models\KeyDate;
use App\Models\User;
use Illuminate\Support\Collection;

class GoogleCalendarService
{
    protected GoogleClient $client;
    protected User $user;

    const BASE_URL = 'https://www.googleapis.com/calendar/v3';

    public function __construct(User $user)
    {
        $this->user   = $user;
        $this->client = new GoogleClient($user);
    }

    /**
     * Return all calendars the user can edit, suitable for a dropdown.
     */
    public function getEditableCalendars(): Collection
    {
        $response = $this->client->get(self::BASE_URL . '/users/me/calendarList');

        if (!$response->successful()) {
            return collect();
        }

        return collect($response->json('items', []))
            ->filter(fn($cal) => in_array($cal['accessRole'] ?? '', ['owner', 'writer']))
            ->map(fn($cal) => [
                'id'      => $cal['id'],
                'summary' => $cal['summary'] ?? $cal['id'],
                'primary' => $cal['primary'] ?? false,
            ])
            ->values();
    }

    /**
     * Create a Google Calendar event for a KeyDate.
     * Returns the Google event ID on success.
     */
    public function createEvent(KeyDate $keyDate, string $calendarId): ?string
    {
        $event = $this->buildEventPayload($keyDate);

        $response = $this->client->post(self::BASE_URL . "/calendars/{$calendarId}/events", $event);

        if ($response->successful()) {
            return $response->json('id');
        }

        return null;
    }

    /**
     * Update an existing Google Calendar event for a KeyDate.
     */
    public function updateEvent(KeyDate $keyDate): bool
    {
        if (!$keyDate->google_calendar_event_id || !$keyDate->google_calendar_id) {
            return false;
        }

        $event    = $this->buildEventPayload($keyDate);
        $calId    = urlencode($keyDate->google_calendar_id);
        $eventId  = $keyDate->google_calendar_event_id;

        $response = $this->client->put(self::BASE_URL . "/calendars/{$calId}/events/{$eventId}", $event);

        return $response->successful();
    }

    /**
     * Delete a Google Calendar event for a KeyDate.
     */
    public function deleteEvent(KeyDate $keyDate): bool
    {
        if (!$keyDate->google_calendar_event_id || !$keyDate->google_calendar_id) {
            return false;
        }

        $calId   = urlencode($keyDate->google_calendar_id);
        $eventId = $keyDate->google_calendar_event_id;

        $response = $this->client->delete(self::BASE_URL . "/calendars/{$calId}/events/{$eventId}");

        return $response->successful() || $response->status() === 410; // 410 = already deleted
    }

    /**
     * Fetch a single event from Google Calendar and check if it differs from local.
     * Returns the remote event data if changed, or null if unchanged or not found.
     */
    public function getRemoteEvent(KeyDate $keyDate): ?array
    {
        if (!$keyDate->google_calendar_event_id || !$keyDate->google_calendar_id) {
            return null;
        }

        $calId   = urlencode($keyDate->google_calendar_id);
        $eventId = $keyDate->google_calendar_event_id;

        $response = $this->client->get(self::BASE_URL . "/calendars/{$calId}/events/{$eventId}");

        if (!$response->successful()) {
            return null;
        }

        return $response->json();
    }

    /**
     * Check all synced KeyDates for a user against Google Calendar.
     * Returns a collection of KeyDates that have been updated remotely.
     */
    public function checkForRemoteChanges(User $user): Collection
    {
        $syncedDates = KeyDate::where('user_id', $user->id)
            ->whereNotNull('google_calendar_event_id')
            ->get();

        $updated = collect();

        foreach ($syncedDates as $keyDate) {
            $remote = $this->getRemoteEvent($keyDate);

            if (!$remote) {
                continue;
            }

            // Extract the date from the remote event
            $remoteDate = $remote['start']['date']
                ?? ($remote['start']['dateTime']
                    ? substr($remote['start']['dateTime'], 0, 10)
                    : null);

            if (!$remoteDate) {
                continue;
            }

            $localDate = $keyDate->date->format('Y-m-d');

            // Extract remote summary/label
            $remoteLabel = $remote['summary'] ?? null;
            $localLabel  = $keyDate->label
                ?? ucfirst(str_replace('_', ' ', $keyDate->type));

            if ($remoteDate !== $localDate || $remoteLabel !== $localLabel) {
                // Apply remote changes to local record
                $keyDate->date  = $remoteDate;
                $keyDate->label = $remoteLabel !== ucfirst(str_replace('_', ' ', $keyDate->type))
                    ? $remoteLabel
                    : $keyDate->label;
                $keyDate->save();
                $updated->push($keyDate);
            }
        }

        return $updated;
    }

    /**
     * Build a Google Calendar event payload from a KeyDate.
     */
    protected function buildEventPayload(KeyDate $keyDate): array
    {
        $label = $keyDate->label
            ?? ucfirst(str_replace('_', ' ', $keyDate->type));

        // Get person names for the event description
        $personNames = $keyDate->persons->map(fn($p) => $p->display_name)->implode(', ');
        $description = $personNames ? "For: {$personNames}" : '';

        $date = $keyDate->year_unknown
            ? $keyDate->date->format('--m-d') // RFC 5545 floating date
            : $keyDate->date->format('Y-m-d');

        $event = [
            'summary'     => $label,
            'description' => $description,
            'start'       => ['date' => $keyDate->year_unknown ? now()->format('Y') . $keyDate->date->format('-m-d') : $date],
            'end'         => ['date' => $keyDate->year_unknown ? now()->format('Y') . $keyDate->date->format('-m-d') : $date],
        ];

        if ($keyDate->is_recurring) {
            $event['recurrence'] = ['RRULE:FREQ=YEARLY'];
        }

        return $event;
    }
}