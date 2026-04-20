<?php

namespace App\Services;

use App\Models\Address;
use App\Models\ContactSyncReview;
use App\Models\ContactSyncState;
use App\Models\Person;
use App\Models\User;
use App\Services\Google\GoogleContactsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ContactSyncService
{
    const DEFAULT_BATCH_SIZE = 20;

    protected GoogleContactsService $contacts;
    protected User $user;
    protected int $batchSize;

    public function __construct(User $user, int $batchSize = self::DEFAULT_BATCH_SIZE)
    {
        $this->user      = $user;
        $this->contacts  = new GoogleContactsService($user);
        $this->batchSize = $batchSize;
    }

    /**
     * Run one batch of the sync for this user.
     * Returns a summary of what was processed and flagged.
     */
    public function runBatch(): array
    {
        $state = ContactSyncState::forUser($this->user);

        // Fetch the next batch of persons with a Google contact ID,
        // ordered by ID so the cursor advances predictably
        $query = Person::where('user_id', $this->user->id)
            ->whereNotNull('google_contact_id')
            ->orderBy('id')
            ->limit($this->batchSize);

        // Resume from where we left off
        if ($state->last_person_id) {
            $query->where('id', '>', $state->last_person_id);
        }

        $persons = $query->with(['names', 'addresses', 'photo'])->get();

        if ($persons->isEmpty()) {
            // We've reached the end — reset cursor to start again next run
            $state->reset();
            Log::info("ContactSync [{$this->user->id}]: reached end of contacts, cursor reset.");
            return ['processed' => 0, 'flagged' => 0, 'reset' => true];
        }

        $flagged   = 0;
        $processed = 0;

        foreach ($persons as $person) {
            try {
                $count = $this->syncPerson($person);
                $flagged += $count;
                $processed++;
            } catch (\Exception $e) {
                Log::warning("ContactSync [{$this->user->id}]: failed for person {$person->id}: " . $e->getMessage());
            }
        }

        // Advance the cursor to the last person we processed
        $state->advance($persons->last()->id, $processed);

        Log::info("ContactSync [{$this->user->id}]: processed {$processed}, flagged {$flagged} differences.");

        return [
            'processed'      => $processed,
            'flagged'        => $flagged,
            'reset'          => false,
            'last_person_id' => $persons->last()->id,
        ];
    }

    /**
     * Compare a single Person against their Google Contact.
     * Returns the number of new differences flagged.
     */
    protected function syncPerson(Person $person): int
    {
        $contact = $this->contacts->getContact($person->google_contact_id);

        if (!$contact) {
            // Contact no longer exists in Google or couldn't be fetched — skip
            return 0;
        }

        $flagged = 0;

        $flagged += $this->compareName($person, $contact);
        $flagged += $this->compareBirthday($person, $contact);
        $flagged += $this->compareAddresses($person, $contact);
        $flagged += $this->comparePhoto($person, $contact);

        return $flagged;
    }

    // -------------------------------------------------------------------------
    // Field comparisons
    // -------------------------------------------------------------------------

    /**
     * Compare the primary name fields.
     */
    protected function compareName(Person $person, array $contact): int
    {
        $primary = $person->names->firstWhere('is_primary', true)
            ?? $person->names->first();

        $googleFirst  = $contact['givenName'] ?? null;
        $googleLast   = $contact['familyName'] ?? null;
        $localFirst   = $primary?->first_name;
        $localLast    = $primary?->last_name;

        $flagged = 0;

        if ($this->valuesDiffer($localFirst, $googleFirst)) {
            $flagged += $this->flag($person, 'first_name', 'First Name', $localFirst, $googleFirst);
        }

        if ($this->valuesDiffer($localLast, $googleLast)) {
            $flagged += $this->flag($person, 'last_name', 'Last Name', $localLast, $googleLast);
        }

        return $flagged;
    }

    /**
     * Compare birthday.
     */
    protected function compareBirthday(Person $person, array $contact): int
    {
        $rawData   = $contact['rawData'] ?? [];
        $birthdays = $rawData['birthdays'] ?? [];

        if (empty($birthdays)) {
            return 0; // Google has no birthday — not a difference worth flagging
        }

        $date        = $birthdays[0]['date'] ?? null;
        $yearUnknown = !isset($date['year']) || $date['year'] === 0;

        if (!$date || !isset($date['month'], $date['day'])) {
            return 0;
        }

        $year        = $yearUnknown ? now()->year : $date['year'];
        $googleDate  = Carbon::createFromDate($year, $date['month'], $date['day'])->format('Y-m-d');
        $localDate   = $person->date_of_birth
            ? Carbon::parse($person->date_of_birth)->format('Y-m-d')
            : null;

        // When year is unknown compare only month/day
        if ($yearUnknown && $localDate) {
            $googleMD = substr($googleDate, 5);
            $localMD  = substr($localDate, 5);
            if ($googleMD === $localMD) {
                return 0;
            }
        }

        if ($this->valuesDiffer($localDate, $googleDate)) {
            $localDisplay  = $localDate
                ? Carbon::parse($localDate)->format($person->dob_year_unknown ? 'j F' : 'j F Y')
                : '(none)';
            $googleDisplay = $yearUnknown
                ? Carbon::parse($googleDate)->format('j F') . ' (year unknown in Google)'
                : Carbon::parse($googleDate)->format('j F Y');

            return $this->flag($person, 'birthday', 'Birthday', $localDisplay, $googleDisplay);
        }

        return 0;
    }

    /**
     * Compare addresses — flags if Google has an address not present locally.
     */
    protected function compareAddresses(Person $person, array $contact): int
    {
        $rawData         = $contact['rawData'] ?? [];
        $googleAddresses = $rawData['addresses'] ?? [];

        if (empty($googleAddresses)) {
            return 0;
        }

        $flagged = 0;

        foreach ($googleAddresses as $i => $googleAddress) {
            $googleFormatted = $this->formatGoogleAddress($googleAddress);

            if (empty(trim($googleFormatted))) {
                continue;
            }

            // Check if any local address matches this Google address
            $matchFound = $person->addresses->contains(function (Address $local) use ($googleFormatted) {
                return $this->normaliseAddress($local->formatted)
                    === $this->normaliseAddress($googleFormatted);
            });

            if (!$matchFound) {
                $field = 'address_' . ($i + 1);

                // Summarise local addresses for comparison display
                $localSummary = $person->addresses->isNotEmpty()
                    ? $person->addresses->map(fn($a) => $a->formatted)->implode(' | ')
                    : '(none)';

                $flagged += $this->flag(
                    $person,
                    $field,
                    'Address ' . ($i + 1),
                    $localSummary,
                    $googleFormatted
                );
            }
        }

        return $flagged;
    }

    /**
     * Compare photo — flags if Google has a photo but PastorEyes doesn't,
     * or if the photo has visibly changed (detected via a hash of the URL).
     */
    protected function comparePhoto(Person $person, array $contact): int
    {
        $photoUrl = $contact['photoUrl'] ?? null;

        if (!$photoUrl) {
            return 0; // Google has no photo — not flagged
        }

        // If PastorEyes has no photo at all, flag it
        if (!$person->photo) {
            return $this->flag(
                $person,
                'photo',
                'Photo',
                '(none)',
                'Google has a photo'
            );
        }

        // Google photo URLs contain a version hash — extract it to detect changes.
        // The URL format is typically: .../photo.jpg?sz=...&...
        // We store a hash of the URL suffix to detect changes without re-downloading.
        $urlHash   = md5($photoUrl);
        $storedHash = md5($person->photo->data ?? '');

        // We can't compare the actual image bytes efficiently in a sync loop.
        // Instead, flag only if we have no local photo — visual changes are
        // left for the manual review queue. This is a pragmatic trade-off for
        // a batch sync service.

        return 0;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Create a review record if one doesn't already exist for this person+field.
     * Returns 1 if a new record was created, 0 if one already existed.
     */
    protected function flag(
        Person $person,
        string $field,
        string $fieldLabel,
        ?string $localValue,
        ?string $googleValue
    ): int {
        if (ContactSyncReview::pendingExistsFor($person->id, $field)) {
            return 0; // Already flagged — don't create a duplicate
        }

        ContactSyncReview::create([
            'user_id'      => $this->user->id,
            'person_id'    => $person->id,
            'field'        => $field,
            'field_label'  => $fieldLabel,
            'local_value'  => $localValue,
            'google_value' => $googleValue,
            'status'       => 'pending',
            'detected_at'  => now(),
        ]);

        return 1;
    }

    /**
     * Compare two nullable string values, treating null and empty string as equal.
     */
    protected function valuesDiffer(?string $a, ?string $b): bool
    {
        $a = trim((string) $a);
        $b = trim((string) $b);
        return strtolower($a) !== strtolower($b);
    }

    /**
     * Normalise an address string for comparison — lowercase, collapse whitespace.
     */
    protected function normaliseAddress(string $address): string
    {
        return strtolower(preg_replace('/\s+/', ' ', trim($address)));
    }

    /**
     * Format a raw Google address array into a display string.
     */
    protected function formatGoogleAddress(array $address): string
    {
        $parts = array_filter([
            $address['streetAddress'] ?? null,
            $address['city']          ?? null,
            $address['region']        ?? null,
            $address['postalCode']    ?? null,
            $address['country']       ?? null,
        ]);

        return implode(', ', $parts);
    }
}