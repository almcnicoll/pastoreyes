<?php

namespace App\Models;

use App\Casts\EncryptedCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    use HasFactory;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'google_oauth_id',
        'google_oauth_token',
        'google_oauth_refresh_token',
        'google_token_expires_at',
        'is_active',
        'is_admin',
        'last_login_at',
        'settings',
    ];

    protected $hidden = [
        'encryption_salt',
        'google_oauth_token',
        'google_oauth_refresh_token',
    ];

    protected function casts(): array
    {
        return [
            'first_name'                 => EncryptedCast::class,
            'last_name'                  => EncryptedCast::class,
            'google_oauth_token'         => EncryptedCast::class,
            'google_oauth_refresh_token' => EncryptedCast::class,
            'google_token_expires_at'    => 'datetime',
            'last_login_at'              => 'datetime',
            'is_active'                  => 'boolean',
            'is_admin'                   => 'boolean',
            'settings'                   => 'array',
        ];
    }

    /**
     * Generate and assign a new encryption salt.
     * Called once on first login — never changed thereafter.
     */
    public function generateEncryptionSalt(): void
    {
        if (empty($this->encryption_salt)) {
            $this->encryption_salt = Str::random(64);
            $this->save();
        }
    }

    // Relationships

    public function persons(): HasMany
    {
        return $this->hasMany(Person::class);
    }

    public function relationshipTypes(): HasMany
    {
        return $this->hasMany(RelationshipType::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }

    public function prayerNeeds(): HasMany
    {
        return $this->hasMany(PrayerNeed::class);
    }

    public function goals(): HasMany
    {
        return $this->hasMany(Goal::class);
    }

    public function outcomes(): HasMany
    {
        return $this->hasMany(Outcome::class);
    }

    public function keyDates(): HasMany
    {
        return $this->hasMany(KeyDate::class);
    }
}
