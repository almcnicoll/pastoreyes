<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * App-wide key/value settings. Currently holds the SMTP configuration
 * (key 'mail', JSON). The SMTP password is encrypted with APP_KEY rather than
 * the per-user EncryptedCast, since it doesn't belong to any one user.
 */
class AppSetting extends Model
{
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['key', 'value'];

    /** SMTP settings as saved, with the password decrypted. Null if never configured. */
    public static function mail(): ?array
    {
        $json = static::find('mail')?->value;
        if (!$json) {
            return null;
        }

        $mail = json_decode($json, true) ?: [];
        if (!empty($mail['password'])) {
            $mail['password'] = Crypt::decryptString($mail['password']);
        }

        return $mail;
    }

    public static function saveMail(array $mail): void
    {
        if (!empty($mail['password'])) {
            $mail['password'] = Crypt::encryptString($mail['password']);
        }

        static::updateOrCreate(['key' => 'mail'], ['value' => json_encode($mail)]);
    }
}
