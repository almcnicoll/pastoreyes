<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use RuntimeException;

class EncryptedCast implements CastsAttributes
{
    /**
     * Derive a per-user encryption key by combining APP_KEY with the user's salt.
     * This means neither APP_KEY alone nor the database alone is sufficient to decrypt.
     */
    protected function deriveKey(Model $model): string
    {
        // If the model itself is a User, use its own salt
        if ($model instanceof \App\Models\User) {
            $salt = $model->encryption_salt;
        } else {
            // Try the authenticated user first
            $user = Auth::user();

            // If not available, try to resolve via the model's user_id
            if (!$user && isset($model->user_id)) {
                $user = \App\Models\User::find($model->user_id);
            }

            // For models that belong to a person (e.g. PersonName, Address),
            // walk up to the person's user
            if (!$user && isset($model->person_id)) {
                $person = \App\Models\Person::find($model->person_id);
                if ($person) {
                    $user = \App\Models\User::find($person->user_id);
                }
            }

            if (!$user || empty($user->encryption_salt)) {
                throw new RuntimeException('Cannot encrypt/decrypt: no authenticated user with encryption salt.');
            }

            $salt = $user->encryption_salt;
        }

        // Combine APP_KEY with the user's salt to derive a unique per-user key
        $appKey = config('app.key');

        // Strip the 'base64:' prefix if present
        if (str_starts_with($appKey, 'base64:')) {
            $appKey = base64_decode(substr($appKey, 7));
        }

        return base64_encode(hash_hmac('sha256', $salt, $appKey, true));
    }

    /**
     * Decrypt the value when retrieving from the database.
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null) {
            return null;
        }

        try {
            $derivedKey = $this->deriveKey($model);
            // Temporarily override the encrypter key for this operation
            $encrypter = new \Illuminate\Encryption\Encrypter(
                base64_decode($derivedKey),
                'AES-256-CBC'
            );
            return $encrypter->decryptString($value);
        } catch (\Exception $e) {
            // Return null rather than exposing encrypted data or crashing
            return null;
        }
    }

    /**
     * Encrypt the value when storing to the database.
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null) {
            return null;
        }

        $derivedKey = $this->deriveKey($model);
        $encrypter = new \Illuminate\Encryption\Encrypter(
            base64_decode($derivedKey),
            'AES-256-CBC'
        );
        return $encrypter->encryptString((string) $value);
    }
}
