<?php

namespace App\Actions;

use App\Models\Address;
use App\Models\KeyDate;
use App\Models\Person;
use App\Models\PersonName;
use App\Services\Google\GoogleContactsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ImportPersonFromGoogle
{
    /**
     * Import a Google Contact as a new Person record.
     * Returns the created Person on success.
     */
    public function execute(string $resourceName): Person
    {
        $service = new GoogleContactsService(auth()->user());

        $contact = $service->getContact($resourceName);

        if (!$contact) {
            throw new \RuntimeException('Could not retrieve contact from Google.');
        }

        return DB::transaction(function () use ($contact, $resourceName) {

            // ----------------------------------------------------------------
            // Create the Person record
            // ----------------------------------------------------------------
            $gender = $this->mapGender($contact);

            $person = Person::create([
                'user_id'           => auth()->id(),
                'google_contact_id' => $resourceName,
                'gender'            => $gender,
            ]);

            // ----------------------------------------------------------------
            // Create PersonName records
            // ----------------------------------------------------------------
            $this->importNames($person, $contact);

            // ----------------------------------------------------------------
            // Import addresses
            // ----------------------------------------------------------------
            $this->importAddresses($person, $contact);

            // ----------------------------------------------------------------
            // Import key dates (birthday, anniversary)
            // ----------------------------------------------------------------
            $this->importKeyDates($person, $contact);

            return $person;
        });
    }

    /**
     * Map Google gender value to our enum.
     */
    protected function mapGender(array $contact): ?string
    {
        $genders = $contact['rawData']['genders'] ?? [];
        $value   = strtolower($genders[0]['value'] ?? '');

        return match($value) {
            'male'   => 'male',
            'female' => 'female',
            default  => null,
        };
    }

    /**
     * Create PersonName records from Google contact name data.
     * Google may provide multiple names (e.g. maiden name via nickname field).
     */
    protected function importNames(Person $person, array $contact): void
    {
        $isPrimary = true;

        // Primary name from displayName/given/family
        $firstName  = $contact['givenName'] ?? null;
        $lastName   = $contact['familyName'] ?? null;
        $nickname   = null;

        // Check raw data for middle name and nickname
        $rawNames = $contact['rawData']['names'] ?? [];
        $primaryRaw = collect($rawNames)->firstWhere('metadata.primary', true)
            ?? ($rawNames[0] ?? []);

        $middleName = $primaryRaw['middleName'] ?? null;
        $nickname   = $primaryRaw['nickname'] ?? null;

        // Also check nicknames field
        $nicknames = $contact['rawData']['nicknames'] ?? [];
        if (!$nickname && !empty($nicknames)) {
            $nickname = $nicknames[0]['value'] ?? null;
        }

        if ($firstName || $lastName) {
            PersonName::create([
                'person_id'      => $person->id,
                'first_name'     => $firstName,
                'last_name'      => $lastName,
                'middle_names'   => $middleName,
                'preferred_name' => $nickname,
                'type'           => 'birth',
                'is_primary'     => true,
            ]);
            $isPrimary = false;
        }

        // Check for maiden name in "fileAs" or additional names
        $additionalNames = collect($rawNames)->filter(
            fn($n) => !($n['metadata']['primary'] ?? false)
        );

        foreach ($additionalNames as $name) {
            $given  = $name['givenName'] ?? null;
            $family = $name['familyName'] ?? null;

            if ($given || $family) {
                PersonName::create([
                    'person_id'  => $person->id,
                    'first_name' => $given,
                    'last_name'  => $family,
                    'type'       => 'other',
                    'is_primary' => false,
                ]);
            }
        }
    }

    /**
     * Import addresses from Google contact.
     */
    protected function importAddresses(Person $person, array $contact): void
    {
        $addresses = $contact['rawData']['addresses'] ?? [];

        foreach ($addresses as $address) {
            Address::create([
                'person_id'  => $person->id,
                'line_1'     => $address['streetAddress'] ?? null,
                'city'       => $address['city'] ?? null,
                'county'     => $address['region'] ?? null,
                'postcode'   => $address['postalCode'] ?? null,
                'country'    => $address['country'] ?? null,
                'date_added' => now()->format('Y-m-d'),
                'is_current' => true,
            ]);
        }
    }

    /**
     * Import key dates (birthdays, anniversaries) from Google contact.
     */
    protected function importKeyDates(Person $person, array $contact): void
    {
        $rawData  = $contact['rawData'] ?? [];
        $birthdays = $rawData['birthdays'] ?? [];
        $events    = $rawData['events'] ?? [];

        // Birthday
        foreach ($birthdays as $birthday) {
            $date = $birthday['date'] ?? null;
            if (!$date) {
                continue;
            }

            $yearUnknown = !isset($date['year']) || $date['year'] === 0;
            $year        = $yearUnknown ? now()->year : $date['year'];
            $month       = $date['month'] ?? 1;
            $day         = $date['day'] ?? 1;

            try {
                $carbonDate = Carbon::createFromDate($year, $month, $day);
            } catch (\Exception $e) {
                continue;
            }

            $kd = KeyDate::create([
                'user_id'      => auth()->id(),
                'date'         => $carbonDate->format('Y-m-d'),
                'year_unknown' => $yearUnknown,
                'type'         => 'birthday',
                'is_recurring' => true,
                'significance' => 3,
                'logged_at'    => now(),
            ]);

            $kd->persons()->attach($person->id, ['is_primary' => true]);
        }

        // Events (anniversaries etc.)
        foreach ($events as $event) {
            $date = $event['date'] ?? null;
            $type = strtolower($event['type'] ?? '');

            if (!$date) {
                continue;
            }

            $keyDateType = match(true) {
                str_contains($type, 'anniversary') => 'wedding_anniversary',
                default                            => 'other',
            };

            $yearUnknown = !isset($date['year']) || $date['year'] === 0;
            $year        = $yearUnknown ? now()->year : $date['year'];
            $month       = $date['month'] ?? 1;
            $day         = $date['day'] ?? 1;

            try {
                $carbonDate = Carbon::createFromDate($year, $month, $day);
            } catch (\Exception $e) {
                continue;
            }

            $label = $event['formattedType'] ?? ucfirst($type) ?: null;

            $kd = KeyDate::create([
                'user_id'      => auth()->id(),
                'date'         => $carbonDate->format('Y-m-d'),
                'year_unknown' => $yearUnknown,
                'type'         => $keyDateType,
                'label'        => $label,
                'is_recurring' => true,
                'significance' => 3,
                'logged_at'    => now(),
            ]);

            $kd->persons()->attach($person->id, ['is_primary' => true]);
        }
    }
}
