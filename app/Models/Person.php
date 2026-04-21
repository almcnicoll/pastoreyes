<?php

namespace App\Models;

use App\Casts\EncryptedCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Person extends Model
{
    use HasFactory;

    protected $table = 'persons';

    protected $fillable = [
        'user_id',
        'google_contact_id',
        'gender',
        'date_of_death',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date_of_death' => EncryptedCast::class,
            'notes'         => EncryptedCast::class,
        ];
    }

    /**
     * Get the birthday KeyDate for this person, if one exists.
     */
    public function getBirthdayAttribute(): ?KeyDate
    {
        return $this->keyDates()->where('type', 'birthday')->first();
    }

    /**
     * Get the primary name for this person.
     */
    public function primaryName(): HasOne
    {
        return $this->hasOne(PersonName::class)->where('is_primary', true);
    }

    /**
     * Get the display name string for this person.
     * Falls back gracefully if no primary name is set.
     */
    public function getDisplayNameAttribute(): string
    {
        $name = $this->primaryName;

        if (!$name) {
            return 'Unknown';
        }

        $parts = array_filter([
            $name->preferred_name ?? $name->first_name,
            $name->last_name,
        ]);

        $display = implode(' ', $parts) ?: 'Unknown';

        if ($name->spelling_uncertain) {
            $display .= ' (?)';
        }

        return $display;
    }

    // Relationships

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function names(): HasMany
    {
        return $this->hasMany(PersonName::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function photo(): HasOne
    {
        return $this->hasOne(PersonPhoto::class);
    }

    public function relationshipsFrom(): HasMany
    {
        return $this->hasMany(Relationship::class, 'person_id');
    }

    public function relationshipsTo(): HasMany
    {
        return $this->hasMany(Relationship::class, 'related_person_id');
    }

    /**
     * All relationships regardless of direction.
     */
    public function allRelationships()
    {
        return Relationship::where('person_id', $this->id)
            ->orWhere('related_person_id', $this->id)
            ->with('relationshipType', 'person', 'relatedPerson');
    }

    // Polymorphic timeline entries

    public function notes(): MorphToMany
    {
        return $this->morphedByMany(Note::class, 'entryable', 'person_entry');
    }

    public function prayerNeeds(): MorphToMany
    {
        return $this->morphedByMany(PrayerNeed::class, 'entryable', 'person_entry');
    }

    public function goals(): MorphToMany
    {
        return $this->morphedByMany(Goal::class, 'entryable', 'person_entry');
    }

    public function outcomes(): MorphToMany
    {
        return $this->morphedByMany(Outcome::class, 'entryable', 'person_entry');
    }

    public function keyDates(): MorphToMany
    {
        return $this->morphedByMany(KeyDate::class, 'entryable', 'person_entry');
    }
}
