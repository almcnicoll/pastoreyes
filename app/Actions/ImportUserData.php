<?php

namespace App\Actions;

use App\Models\Address;
use App\Models\Goal;
use App\Models\KeyDate;
use App\Models\Note;
use App\Models\Outcome;
use App\Models\Person;
use App\Models\PersonName;
use App\Models\PersonPhoto;
use App\Models\PrayerNeed;
use App\Models\Relationship;
use App\Models\RelationshipType;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ImportUserData
{
    // Maps old IDs to new IDs for each model type
    protected array $idMap = [];

    /**
     * Import data from an encrypted export file into a target user's account.
     *
     * @param string $encryptedData  Base64-encoded encrypted export
     * @param string $passphrase     Passphrase used during export
     * @param User   $targetUser     The user to import into
     * @param bool   $replaceExisting  If true, wipe existing data first
     */
    public function execute(
        string $encryptedData,
        string $passphrase,
        User $targetUser,
        bool $replaceExisting = false
    ): array {
        // Decrypt and parse
        $json = $this->decrypt($encryptedData, $passphrase);
        $payload = json_decode($json, true);

        if (!$payload || ($payload['app'] ?? '') !== 'PastorEyes') {
            throw new \RuntimeException('Invalid export file.');
        }

        $data    = $payload['data'] ?? [];
        $summary = [];

        DB::transaction(function () use ($data, $targetUser, $replaceExisting, &$summary) {

            if ($replaceExisting) {
                $this->wipeUserData($targetUser);
            }

            // Import in dependency order
            $summary['persons']            = $this->importPersons($data['persons'] ?? [], $targetUser);
            $summary['person_names']       = $this->importPersonNames($data['person_names'] ?? [], $targetUser);
            $summary['person_photos']      = $this->importPersonPhotos($data['person_photos'] ?? [], $targetUser);
            $summary['addresses']          = $this->importAddresses($data['addresses'] ?? [], $targetUser);
            $summary['relationship_types'] = $this->importRelationshipTypes($data['relationship_types'] ?? [], $targetUser);
            $summary['relationships']      = $this->importRelationships($data['relationships'] ?? [], $targetUser);
            $summary['notes']              = $this->importNotes($data['notes'] ?? [], $targetUser);
            $summary['prayer_needs']       = $this->importPrayerNeeds($data['prayer_needs'] ?? [], $targetUser);
            $summary['goals']              = $this->importGoals($data['goals'] ?? [], $targetUser);
            $summary['outcomes']           = $this->importOutcomes($data['outcomes'] ?? [], $targetUser);
            $summary['key_dates']          = $this->importKeyDates($data['key_dates'] ?? [], $targetUser);
            $this->importPersonEntry($data['person_entry'] ?? []);

        });

        return $summary;
    }

    protected function wipeUserData(User $user): void
    {
        // Cascade deletes handle related records via foreign keys
        Person::where('user_id', $user->id)->delete();
        Note::where('user_id', $user->id)->delete();
        PrayerNeed::where('user_id', $user->id)->delete();
        Goal::where('user_id', $user->id)->delete();
        Outcome::where('user_id', $user->id)->delete();
        KeyDate::where('user_id', $user->id)->delete();
        Relationship::where('user_id', $user->id)->delete();
        RelationshipType::where('user_id', $user->id)->delete();
    }

    protected function importPersons(array $records, User $user): int
    {
        foreach ($records as $record) {
            $person = Person::create([
                'user_id'          => $user->id,
                'google_contact_id' => $record['google_contact_id'] ?? null,
                'gender'           => $record['gender'] ?? null,
                'date_of_birth'    => $record['date_of_birth'] ?? null,
                'dob_year_unknown' => $record['dob_year_unknown'] ?? false,
                'date_of_death'    => $record['date_of_death'] ?? null,
                'notes'            => $record['notes'] ?? null,
            ]);
            $this->idMap['persons'][$record['id']] = $person->id;
        }
        return count($records);
    }

    protected function importPersonNames(array $records, User $user): int
    {
        foreach ($records as $record) {
            $newPersonId = $this->idMap['persons'][$record['person_id']] ?? null;
            if (!$newPersonId) continue;

            PersonName::create([
                'person_id'        => $newPersonId,
                'first_name'       => $record['first_name'] ?? null,
                'last_name'        => $record['last_name'] ?? null,
                'middle_names'     => $record['middle_names'] ?? null,
                'preferred_name'   => $record['preferred_name'] ?? null,
                'type'             => $record['type'] ?? 'birth',
                'spelling_uncertain' => $record['spelling_uncertain'] ?? false,
                'date_from'        => $record['date_from'] ?? null,
                'date_to'          => $record['date_to'] ?? null,
                'is_primary'       => $record['is_primary'] ?? false,
                'notes'            => $record['notes'] ?? null,
            ]);
        }
        return count($records);
    }

    protected function importPersonPhotos(array $records, User $user): int
    {
        foreach ($records as $record) {
            $newPersonId = $this->idMap['persons'][$record['person_id']] ?? null;
            if (!$newPersonId) continue;

            PersonPhoto::create([
                'person_id' => $newPersonId,
                'data'      => $record['data'],
                'mime_type' => $record['mime_type'],
                'file_size' => $record['file_size'],
            ]);
        }
        return count($records);
    }

    protected function importAddresses(array $records, User $user): int
    {
        foreach ($records as $record) {
            $newPersonId = $this->idMap['persons'][$record['person_id']] ?? null;
            if (!$newPersonId) continue;

            Address::create([
                'person_id'  => $newPersonId,
                'line_1'     => $record['line_1'] ?? null,
                'line_2'     => $record['line_2'] ?? null,
                'line_3'     => $record['line_3'] ?? null,
                'city'       => $record['city'] ?? null,
                'county'     => $record['county'] ?? null,
                'postcode'   => $record['postcode'] ?? null,
                'country'    => $record['country'] ?? null,
                'date_added' => $record['date_added'] ?? now()->toDateString(),
                'is_current' => $record['is_current'] ?? true,
                'notes'      => $record['notes'] ?? null,
            ]);
        }
        return count($records);
    }

    protected function importRelationshipTypes(array $records, User $user): int
    {
        foreach ($records as $record) {
            $rt = RelationshipType::create([
                'user_id'        => $user->id,
                'name'           => $record['name'],
                'inverse_name'   => $record['inverse_name'] ?? null,
                'is_directional' => $record['is_directional'] ?? false,
                'is_preset'      => false,
            ]);
            $this->idMap['relationship_types'][$record['id']] = $rt->id;
        }
        return count($records);
    }

    protected function importRelationships(array $records, User $user): int
    {
        foreach ($records as $record) {
            $newPersonId        = $this->idMap['persons'][$record['person_id']] ?? null;
            $newRelatedPersonId = $this->idMap['persons'][$record['related_person_id']] ?? null;

            if (!$newPersonId || !$newRelatedPersonId) continue;

            // Remap relationship type ID — check custom map first, then use original
            // (global/preset types keep their original IDs)
            $newTypeId = $this->idMap['relationship_types'][$record['relationship_type_id']]
                ?? $record['relationship_type_id'];

            // Verify the type exists
            if (!RelationshipType::find($newTypeId)) continue;

            Relationship::create([
                'user_id'              => $user->id,
                'person_id'            => $newPersonId,
                'related_person_id'    => $newRelatedPersonId,
                'relationship_type_id' => $newTypeId,
                'notes'                => $record['notes'] ?? null,
                'date_from'            => $record['date_from'] ?? null,
                'date_to'              => $record['date_to'] ?? null,
            ]);
        }
        return count($records);
    }

    protected function importNotes(array $records, User $user): int
    {
        foreach ($records as $record) {
            $note = Note::create([
                'user_id'     => $user->id,
                'date'        => $record['date'],
                'logged_at'   => $record['logged_at'] ?? now(),
                'title'       => $record['title'] ?? null,
                'body'        => $record['body'],
                'significance' => $record['significance'],
            ]);
            $this->idMap['notes'][$record['id']] = $note->id;
        }
        return count($records);
    }

    protected function importPrayerNeeds(array $records, User $user): int
    {
        foreach ($records as $record) {
            $need = PrayerNeed::create([
                'user_id'            => $user->id,
                'date'               => $record['date'],
                'logged_at'          => $record['logged_at'] ?? now(),
                'title'              => $record['title'] ?? null,
                'body'               => $record['body'],
                'resolved_at'        => $record['resolved_at'] ?? null,
                'resolution_details' => $record['resolution_details'] ?? null,
                'significance'       => $record['significance'],
            ]);
            $this->idMap['prayer_needs'][$record['id']] = $need->id;
        }
        return count($records);
    }

    protected function importGoals(array $records, User $user): int
    {
        foreach ($records as $record) {
            $goal = Goal::create([
                'user_id'     => $user->id,
                'date'        => $record['date'],
                'logged_at'   => $record['logged_at'] ?? now(),
                'title'       => $record['title'],
                'body'        => $record['body'],
                'target_date' => $record['target_date'] ?? null,
                'achieved_at' => $record['achieved_at'] ?? null,
                'significance' => $record['significance'],
            ]);
            $this->idMap['goals'][$record['id']] = $goal->id;
        }
        return count($records);
    }

    protected function importOutcomes(array $records, User $user): int
    {
        foreach ($records as $record) {
            $newGoalId = $this->idMap['goals'][$record['goal_id']] ?? null;
            if (!$newGoalId) continue;

            $outcome = Outcome::create([
                'user_id'     => $user->id,
                'goal_id'     => $newGoalId,
                'date'        => $record['date'],
                'logged_at'   => $record['logged_at'] ?? now(),
                'title'       => $record['title'],
                'body'        => $record['body'],
                'significance' => $record['significance'],
            ]);
            $this->idMap['outcomes'][$record['id']] = $outcome->id;
        }
        return count($records);
    }

    protected function importKeyDates(array $records, User $user): int
    {
        foreach ($records as $record) {
            $kd = KeyDate::create([
                'user_id'      => $user->id,
                'date'         => $record['date'],
                'year_unknown' => $record['year_unknown'] ?? false,
                'logged_at'    => $record['logged_at'] ?? now(),
                'type'         => $record['type'],
                'label'        => $record['label'] ?? null,
                'is_recurring' => $record['is_recurring'] ?? true,
                'significance' => $record['significance'],
                // Don't import Google Calendar sync IDs — they're environment-specific
                'google_calendar_event_id' => null,
                'google_calendar_id'       => null,
            ]);
            $this->idMap['key_dates'][$record['id']] = $kd->id;
        }
        return count($records);
    }

    protected function importPersonEntry(array $records): void
    {
        // Map entryable_type to the correct idMap key
        $typeMap = [
            'App\\Models\\Note'       => 'notes',
            'App\\Models\\PrayerNeed' => 'prayer_needs',
            'App\\Models\\Goal'       => 'goals',
            'App\\Models\\Outcome'    => 'outcomes',
            'App\\Models\\KeyDate'    => 'key_dates',
        ];

        foreach ($records as $record) {
            $newPersonId = $this->idMap['persons'][$record['person_id']] ?? null;
            if (!$newPersonId) continue;

            $mapKey      = $typeMap[$record['entryable_type']] ?? null;
            $newEntryId  = $mapKey
                ? ($this->idMap[$mapKey][$record['entryable_id']] ?? null)
                : null;

            if (!$newEntryId) continue;

            DB::table('person_entry')->insertOrIgnore([
                'person_id'      => $newPersonId,
                'entryable_id'   => $newEntryId,
                'entryable_type' => $record['entryable_type'],
                'is_primary'     => $record['is_primary'] ?? true,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
        }
    }

    /**
     * Decrypt an export file using the supplied passphrase.
     */
    protected function decrypt(string $encryptedData, string $passphrase): string
    {
        $packed = base64_decode($encryptedData);

        if ($packed === false || strlen($packed) < 50) {
            throw new \RuntimeException('Invalid or corrupted export file.');
        }

        $version = ord($packed[0]);

        if ($version !== 1) {
            throw new \RuntimeException('Unsupported export file version.');
        }

        $salt       = substr($packed, 1, 32);
        $iv         = substr($packed, 33, 16);
        $ciphertext = substr($packed, 49);

        $key = hash_pbkdf2('sha256', $passphrase, $salt, 100000, 32, true);

        $plaintext = openssl_decrypt($ciphertext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

        if ($plaintext === false) {
            throw new \RuntimeException('Decryption failed — incorrect passphrase or corrupted file.');
        }

        return $plaintext;
    }
}
