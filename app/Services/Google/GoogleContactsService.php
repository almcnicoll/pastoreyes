<?php

namespace App\Services\Google;

use App\Models\Person;
use App\Models\User;
use Illuminate\Support\Collection;

class GoogleContactsService
{
    protected GoogleClient $client;
    protected User $user;

    const BASE_URL = 'https://people.googleapis.com/v1';

    public function __construct(User $user)
    {
        $this->user   = $user;
        $this->client = new GoogleClient($user);
    }

    /**
     * Search Google Contacts by name query.
     * Returns a collection of matching contacts for display in the link modal.
     */
    public function searchContacts(string $query): Collection
    {
        $response = $this->client->http()
            ->get(self::BASE_URL . '/people:searchContacts', [
                'query'      => $query,
                'readMask'   => 'names,emailAddresses,phoneNumbers',
                'pageSize'   => 10,
            ]);
        \Log::debug('Google contacts raw response', ['body' => $response]);

        if (!$response->successful()) {
            return collect();
        }

        return collect($response->json('results', []))
            ->map(fn($result) => $this->formatContact($result['person'] ?? []))
            ->filter()
            ->values();
    }

    /**
     * Get a single contact by resource name (e.g. 'people/c12345').
     */
    public function getContact(string $resourceName): ?array
    {
        $response = $this->client->http()
            ->get(self::BASE_URL . '/' . $resourceName, [
                'personFields' => 'names,emailAddresses,phoneNumbers,addresses,photos,genders,birthdays,events,nicknames',
            ]);

        if (!$response->successful()) {
            return null;
        }

        return $this->formatContact($response->json());
    }

    /**
     * Get addresses from a Google Contact and compare with local addresses.
     * Returns any addresses found in Google that are not stored locally.
     */
    public function getMissingAddresses(Person $person): Collection
    {
        if (!$person->google_contact_id) {
            return collect();
        }

        $contact = $this->getContact($person->google_contact_id);

        if (!$contact || empty($contact['addresses'])) {
            return collect();
        }

        $localAddresses = $person->addresses->map(fn($a) => $this->normaliseAddress([
            'streetAddress' => trim(implode(', ', array_filter([
                $a->line_1, $a->line_2, $a->line_3,
            ]))),
            'city'           => $a->city,
            'region'         => $a->county,
            'postalCode'     => $a->postcode,
            'country'        => $a->country,
        ]));

        return collect($contact['addresses'])->filter(function ($googleAddress) use ($localAddresses) {
            $normalised = $this->normaliseAddress($googleAddress);
            return !$localAddresses->contains($normalised);
        })->values();
    }

    /**
     * Format a raw Google People API person object into a clean array.
     */
    protected function formatContact(array $person): ?array
    {
        if (empty($person)) {
            return null;
        }

        $name = collect($person['names'] ?? [])
            ->firstWhere('metadata.primary', true)
            ?? ($person['names'][0] ?? null);

        $photo = collect($person['photos'] ?? [])
            ->firstWhere('metadata.primary', true)
            ?? ($person['photos'][0] ?? null);

        return [
            'resourceName'  => $person['resourceName'] ?? null,
            'displayName'   => $name['displayName'] ?? 'Unknown',
            'givenName'     => $name['givenName'] ?? null,
            'familyName'    => $name['familyName'] ?? null,
            'photoUrl'      => $photo['url'] ?? null,
            'emails'        => collect($person['emailAddresses'] ?? [])->pluck('value')->toArray(),
            'phones'        => collect($person['phoneNumbers'] ?? [])->pluck('value')->toArray(),
            'addresses'     => $person['addresses'] ?? [],
            'rawData'       => $person, // full raw response for import
        ];
    }

    /**
     * Normalise an address array to a comparable string.
     */
    protected function normaliseAddress(array $address): string
    {
        return strtolower(trim(implode(' ', array_filter([
            $address['streetAddress'] ?? null,
            $address['city']          ?? null,
            $address['region']        ?? null,
            $address['postalCode']    ?? null,
            $address['country']       ?? null,
        ]))));
    }
}