<?php

namespace App\Services;

use App\Models\Address;
use App\Models\ContactSyncReview;
use App\Models\KeyDate;
use App\Models\Person;
use App\Models\PersonPhoto;
use App\Services\Google\GoogleContactsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ContactSyncResolutionService
{
    protected GoogleContactsService $contacts;

    public function __construct()
    {
        $this->contacts = new GoogleContactsService(auth()->user());
    }

    /**
     * Resolve a review by pulling the Google value into PastorEyes.
     */
    public function pullToLocal(ContactSyncReview $review): void
    {
        $person = $review->person->load(['names', 'addresses', 'photo']);

        match(true) {
            $review->field === 'first_name'            => $this->pullFirstName($person, $review),
            $review->field === 'last_name'             => $this->pullLastName($person, $review),
            $review->field === 'birthday'              => $this->pullBirthday($person, $review),
            $review->field === 'photo'                 => $this->pullPhoto($person, $review),
            str_starts_with($review->field, 'address') => $this->pullAddress($person, $review),
            default => null,
        };

        $review->resolve('pulled_to_local');
    }

    /**
     * Resolve a review by pushing the PastorEyes value to Google.
     */
    public function pushToGoogle(ContactSyncReview $review): void
    {
        $person = $review->person->load(['names', 'addresses', 'photo']);

        match(true) {
            in_array($review->field, ['first_name', 'last_name']) => $this->pushNameToGoogle($person, $review),
            $review->field === 'birthday'                         => $this->pushBirthdayToGoogle($person, $review),
            str_starts_with($review->field, 'address')           => $this->pushAddressToGoogle($person, $review),
            $review->field === 'photo'                            => null, // can't push photo to Google via People API
            default                                               => null,
        };

        $review->resolve('pushed_to_google');
    }

    /**
     * Mark a review as ignored — the difference will not be flagged again
     * unless the value changes again in a future sync.
     */
    public function ignore(ContactSyncReview $review): void
    {
        $review->resolve('ignored');
    }

    // -------------------------------------------------------------------------
    // Pull implementations (Google → PastorEyes)
    // -------------------------------------------------------------------------

    protected function pullFirstName(Person $person, ContactSyncReview $review): void
    {
        $primary = $person->names->firstWhere('is_primary', true)
            ?? $person->names->first();

        if ($primary) {
            $primary->update(['first_name' => $review->google_value]);
        }
    }

    protected function pullLastName(Person $person, ContactSyncReview $review): void
    {
        $primary = $person->names->firstWhere('is_primary', true)
            ?? $person->names->first();

        if ($primary) {
            $primary->update(['last_name' => $review->google_value]);
        }
    }

    protected function pullBirthday(Person $person, ContactSyncReview $review): void
    {
        $googleValue = $review->google_value ?? '';
        $yearUnknown = str_contains($googleValue, 'year unknown');
        $datePart    = str_replace(' (year unknown in Google)', '', $googleValue);

        try {
            $date = Carbon::parse($datePart);

            $existingKd = $person->keyDates()->where('type', 'birthday')->first();

            $data = [
                'user_id'      => $person->user_id,
                'date'         => $date->format('Y-m-d'),
                'year_unknown' => $yearUnknown,
                'type'         => 'birthday',
                'is_recurring' => true,
                'significance' => 3,
                'logged_at'    => now(),
            ];

            if ($existingKd) {
                $existingKd->update($data);
            } else {
                $kd = \App\Models\KeyDate::create($data);
                $kd->persons()->attach($person->id, ['is_primary' => true]);
            }
        } catch (\Exception $e) {
            Log::warning('ContactSyncResolution: failed to parse birthday "' . $googleValue . '": ' . $e->getMessage());
        }
    }

    protected function pullPhoto(Person $person, ContactSyncReview $review): void
    {
        // Re-fetch the contact to get the current photo URL
        $contact  = $this->contacts->getContact($person->google_contact_id);
        $photoUrl = $contact['photoUrl'] ?? null;

        if (!$photoUrl) {
            return;
        }

        try {
            $response = Http::timeout(15)->get($photoUrl);
            if (!$response->successful()) {
                return;
            }

            $bytes    = $response->body();
            $mimeType = explode(';', $response->header('Content-Type') ?? 'image/jpeg')[0];
            $base64   = base64_encode($bytes);
            $fileSize = strlen($bytes);

            if ($person->photo) {
                $person->photo->update([
                    'data'      => $base64,
                    'mime_type' => $mimeType,
                    'file_size' => $fileSize,
                ]);
            } else {
                PersonPhoto::create([
                    'person_id' => $person->id,
                    'data'      => $base64,
                    'mime_type' => $mimeType,
                    'file_size' => $fileSize,
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('ContactSyncResolution: failed to pull photo for person ' . $person->id . ': ' . $e->getMessage());
        }
    }

    protected function pullAddress(Person $person, ContactSyncReview $review): void
    {
        // Parse the Google address value (comma-separated parts)
        $parts = array_map('trim', explode(',', $review->google_value ?? ''));

        if (empty(array_filter($parts))) {
            return;
        }

        // Mark all existing addresses as not current
        Address::where('person_id', $person->id)->update(['is_current' => false]);

        // Map parts heuristically: street, city, county, postcode, country
        Address::create([
            'person_id'  => $person->id,
            'line_1'     => $parts[0] ?? null,
            'line_2'     => $parts[1] ?? null,
            'city'       => $parts[2] ?? null,
            'county'     => $parts[3] ?? null,
            'postcode'   => $parts[4] ?? null,
            'country'    => $parts[5] ?? null,
            'date_added' => now()->format('Y-m-d'),
            'is_current' => true,
            'notes'      => 'Imported from Google Contacts',
        ]);
    }

    // -------------------------------------------------------------------------
    // Push implementations (PastorEyes → Google)
    // -------------------------------------------------------------------------

    protected function pushNameToGoogle(Person $person, ContactSyncReview $review): void
    {
        $primary = $person->names->firstWhere('is_primary', true)
            ?? $person->names->first();

        if (!$primary || !$person->google_contact_id) {
            return;
        }

        $contact = $this->contacts->getContact($person->google_contact_id);
        if (!$contact) {
            return;
        }

        $rawData     = $contact['rawData'] ?? [];
        $etag        = $rawData['etag'] ?? null;
        $primaryName = collect($rawData['names'] ?? [])->firstWhere('metadata.primary', true)
            ?? ($rawData['names'][0] ?? []);

        $updatedName = array_merge($primaryName, [
            'givenName'  => $primary->first_name ?? '',
            'familyName' => $primary->last_name ?? '',
        ]);

        $this->contacts->updateContactFields(
            $person->google_contact_id,
            ['names' => [$updatedName]],
            $etag,
            'names'
        );
    }

    protected function pushBirthdayToGoogle(Person $person, ContactSyncReview $review): void
    {
        $birthdayKd = $person->keyDates()->where('type', 'birthday')->first();

        if (!$birthdayKd || !$person->google_contact_id) {
            return;
        }

        $contact = $this->contacts->getContact($person->google_contact_id);
        if (!$contact) {
            return;
        }

        $rawData = $contact['rawData'] ?? [];
        $etag    = $rawData['etag'] ?? null;
        $date    = $birthdayKd->date;

        $birthday = $birthdayKd->year_unknown
            ? ['month' => (int) $date->month, 'day' => (int) $date->day]
            : ['year' => (int) $date->year, 'month' => (int) $date->month, 'day' => (int) $date->day];

        $this->contacts->updateContactFields(
            $person->google_contact_id,
            ['birthdays' => [['date' => $birthday]]],
            $etag,
            'birthdays'
        );
    }

    protected function pushAddressToGoogle(Person $person, ContactSyncReview $review): void
    {
        $current = $person->addresses->firstWhere('is_current', true);

        if (!$current || !$person->google_contact_id) {
            return;
        }

        $contact = $this->contacts->getContact($person->google_contact_id);
        if (!$contact) {
            return;
        }

        $rawData = $contact['rawData'] ?? [];
        $etag    = $rawData['etag'] ?? null;

        $addressParts = array_filter([
            $current->line_1,
            $current->line_2,
            $current->line_3,
        ]);

        $googleAddress = [
            'streetAddress' => implode("\n", $addressParts),
            'city'          => $current->city ?? '',
            'region'        => $current->county ?? '',
            'postalCode'    => $current->postcode ?? '',
            'country'       => $current->country ?? '',
            'type'          => 'home',
        ];

        $this->contacts->updateContactFields(
            $person->google_contact_id,
            ['addresses' => [$googleAddress]],
            $etag,
            'addresses'
        );
    }
}
