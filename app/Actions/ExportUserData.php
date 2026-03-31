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
use App\Models\RelationshipType;
use App\Models\Relationship;
use App\Models\User;
use Illuminate\Support\Collection;

class ExportUserData
{
    const VERSION = '1.0';

    /**
     * Export all data for a user as an encrypted JSON blob.
     * Returns the encrypted string ready for download.
     */
    public function execute(User $user, string $passphrase): string
    {
        $data = [
            'version'     => self::VERSION,
            'app'         => 'PastorEyes',
            'exported_at' => now()->toIso8601String(),
            'data'        => [
                'persons'            => $this->exportPersons($user),
                'person_names'       => $this->exportPersonNames($user),
                'person_photos'      => $this->exportPersonPhotos($user),
                'addresses'          => $this->exportAddresses($user),
                'relationship_types' => $this->exportRelationshipTypes($user),
                'relationships'      => $this->exportRelationships($user),
                'notes'              => $this->exportNotes($user),
                'prayer_needs'       => $this->exportPrayerNeeds($user),
                'goals'              => $this->exportGoals($user),
                'outcomes'           => $this->exportOutcomes($user),
                'key_dates'          => $this->exportKeyDates($user),
                'person_entry'       => $this->exportPersonEntry($user),
            ],
        ];

        $json = json_encode($data, JSON_PRETTY_PRINT);

        return $this->encrypt($json, $passphrase);
    }

    protected function exportPersons(User $user): array
    {
        return Person::where('user_id', $user->id)->get()->map(fn($p) => [
            'id'               => $p->id,
            'google_contact_id' => $p->google_contact_id,
            'gender'           => $p->gender,
            'date_of_birth'    => $p->date_of_birth,   // already decrypted by cast
            'dob_year_unknown' => $p->dob_year_unknown,
            'date_of_death'    => $p->date_of_death,
            'notes'            => $p->notes,
        ])->toArray();
    }

    protected function exportPersonNames(User $user): array
    {
        $personIds = Person::where('user_id', $user->id)->pluck('id');

        return PersonName::whereIn('person_id', $personIds)->get()->map(fn($n) => [
            'id'               => $n->id,
            'person_id'        => $n->person_id,
            'first_name'       => $n->first_name,
            'last_name'        => $n->last_name,
            'middle_names'     => $n->middle_names,
            'preferred_name'   => $n->preferred_name,
            'type'             => $n->type,
            'spelling_uncertain' => $n->spelling_uncertain,
            'date_from'        => $n->date_from,
            'date_to'          => $n->date_to,
            'is_primary'       => $n->is_primary,
            'notes'            => $n->notes,
        ])->toArray();
    }

    protected function exportPersonPhotos(User $user): array
    {
        $personIds = Person::where('user_id', $user->id)->pluck('id');

        return PersonPhoto::whereIn('person_id', $personIds)->get()->map(fn($p) => [
            'id'        => $p->id,
            'person_id' => $p->person_id,
            'data'      => $p->data,       // decrypted base64 image data
            'mime_type' => $p->mime_type,
            'file_size' => $p->file_size,
        ])->toArray();
    }

    protected function exportAddresses(User $user): array
    {
        $personIds = Person::where('user_id', $user->id)->pluck('id');

        return Address::whereIn('person_id', $personIds)->get()->map(fn($a) => [
            'id'         => $a->id,
            'person_id'  => $a->person_id,
            'line_1'     => $a->line_1,
            'line_2'     => $a->line_2,
            'line_3'     => $a->line_3,
            'city'       => $a->city,
            'county'     => $a->county,
            'postcode'   => $a->postcode,
            'country'    => $a->country,
            'date_added' => $a->date_added,
            'is_current' => $a->is_current,
            'notes'      => $a->notes,
        ])->toArray();
    }

    protected function exportRelationshipTypes(User $user): array
    {
        // Only export user-specific custom types, not global presets
        return RelationshipType::where('user_id', $user->id)->get()->map(fn($rt) => [
            'id'             => $rt->id,
            'name'           => $rt->name,
            'inverse_name'   => $rt->inverse_name,
            'is_directional' => $rt->is_directional,
        ])->toArray();
    }

    protected function exportRelationships(User $user): array
    {
        return Relationship::where('user_id', $user->id)->get()->map(fn($r) => [
            'id'                   => $r->id,
            'person_id'            => $r->person_id,
            'related_person_id'    => $r->related_person_id,
            'relationship_type_id' => $r->relationship_type_id,
            'notes'                => $r->notes,
            'date_from'            => $r->date_from,
            'date_to'              => $r->date_to,
        ])->toArray();
    }

    protected function exportNotes(User $user): array
    {
        return Note::where('user_id', $user->id)->get()->map(fn($n) => [
            'id'           => $n->id,
            'date'         => $n->date?->toDateString(),
            'logged_at'    => $n->logged_at?->toIso8601String(),
            'title'        => $n->title,
            'body'         => $n->body,
            'significance' => $n->significance,
        ])->toArray();
    }

    protected function exportPrayerNeeds(User $user): array
    {
        return PrayerNeed::where('user_id', $user->id)->get()->map(fn($p) => [
            'id'                 => $p->id,
            'date'               => $p->date?->toDateString(),
            'logged_at'          => $p->logged_at?->toIso8601String(),
            'title'              => $p->title,
            'body'               => $p->body,
            'resolved_at'        => $p->resolved_at?->toDateString(),
            'resolution_details' => $p->resolution_details,
            'significance'       => $p->significance,
        ])->toArray();
    }

    protected function exportGoals(User $user): array
    {
        return Goal::where('user_id', $user->id)->get()->map(fn($g) => [
            'id'           => $g->id,
            'date'         => $g->date?->toDateString(),
            'logged_at'    => $g->logged_at?->toIso8601String(),
            'title'        => $g->title,
            'body'         => $g->body,
            'target_date'  => $g->target_date?->toDateString(),
            'achieved_at'  => $g->achieved_at?->toDateString(),
            'significance' => $g->significance,
        ])->toArray();
    }

    protected function exportOutcomes(User $user): array
    {
        return Outcome::where('user_id', $user->id)->get()->map(fn($o) => [
            'id'           => $o->id,
            'goal_id'      => $o->goal_id,
            'date'         => $o->date?->toDateString(),
            'logged_at'    => $o->logged_at?->toIso8601String(),
            'title'        => $o->title,
            'body'         => $o->body,
            'significance' => $o->significance,
        ])->toArray();
    }

    protected function exportKeyDates(User $user): array
    {
        return KeyDate::where('user_id', $user->id)->get()->map(fn($k) => [
            'id'                      => $k->id,
            'date'                    => $k->date?->toDateString(),
            'year_unknown'            => $k->year_unknown,
            'logged_at'               => $k->logged_at?->toIso8601String(),
            'type'                    => $k->type,
            'label'                   => $k->label,
            'is_recurring'            => $k->is_recurring,
            'significance'            => $k->significance,
            'google_calendar_event_id' => $k->google_calendar_event_id,
            'google_calendar_id'      => $k->google_calendar_id,
        ])->toArray();
    }

    protected function exportPersonEntry(User $user): array
    {
        // Get all person_entry rows for this user's persons and entries
        $personIds = Person::where('user_id', $user->id)->pluck('id');

        return \DB::table('person_entry')
            ->whereIn('person_id', $personIds)
            ->get()
            ->map(fn($row) => [
                'person_id'      => $row->person_id,
                'entryable_id'   => $row->entryable_id,
                'entryable_type' => $row->entryable_type,
                'is_primary'     => $row->is_primary,
            ])->toArray();
    }

    /**
     * Encrypt the JSON using AES-256-CBC with a PBKDF2-derived key from the passphrase.
     * Returns a base64-encoded string containing salt + IV + ciphertext.
     */
    protected function encrypt(string $plaintext, string $passphrase): string
    {
        $salt   = random_bytes(32);
        $iv     = random_bytes(16);
        $key    = hash_pbkdf2('sha256', $passphrase, $salt, 100000, 32, true);

        $cipher = openssl_encrypt($plaintext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

        if ($cipher === false) {
            throw new \RuntimeException('Export encryption failed.');
        }

        // Pack: version byte + salt (32) + iv (16) + ciphertext
        $packed = chr(1) . $salt . $iv . $cipher;

        return base64_encode($packed);
    }
}
