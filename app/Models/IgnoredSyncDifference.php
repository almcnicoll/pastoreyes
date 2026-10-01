<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IgnoredSyncDifference extends Model
{
    protected $fillable = ['user_id', 'person_id', 'field', 'hash'];

    /**
     * Keyed digest of a difference. Values are normalised the same way the sync
     * compares them (trimmed, case-insensitive), and keyed with APP_KEY plus the
     * user's salt so the table doesn't expose guessable contact data.
     */
    public static function hashFor(User $user, string $field, ?string $local, ?string $google): string
    {
        $key  = hash_hmac('sha256', (string) $user->encryption_salt, config('app.key'));
        $data = implode('|', [
            $field,
            strtolower(trim((string) $local)),
            strtolower(trim((string) $google)),
        ]);

        return hash_hmac('sha256', $data, $key);
    }

    public static function isIgnored(User $user, int $personId, string $field, ?string $local, ?string $google): bool
    {
        return static::where('person_id', $personId)
            ->where('field', $field)
            ->where('hash', static::hashFor($user, $field, $local, $google))
            ->exists();
    }

    public static function remember(User $user, int $personId, string $field, ?string $local, ?string $google): void
    {
        static::firstOrCreate([
            'user_id'   => $user->id,
            'person_id' => $personId,
            'field'     => $field,
            'hash'      => static::hashFor($user, $field, $local, $google),
        ]);
    }
}
