<?php

namespace App\Models;

use App\Casts\EncryptedCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonName extends Model
{
    use HasFactory;

    protected $fillable = [
        'person_id',
        'first_name',
        'last_name',
        'middle_names',
        'preferred_name',
        'type',
        'spelling_uncertain',
        'date_from',
        'date_to',
        'is_primary',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'first_name'       => EncryptedCast::class,
            'last_name'        => EncryptedCast::class,
            'middle_names'     => EncryptedCast::class,
            'preferred_name'   => EncryptedCast::class,
            'date_from'        => EncryptedCast::class,
            'date_to'          => EncryptedCast::class,
            'notes'            => EncryptedCast::class,
            'spelling_uncertain' => 'boolean',
            'is_primary'       => 'boolean',
        ];
    }

    /**
     * Ensure only one name per person can be primary.
     * Call this when setting a name as primary.
     */
    public function setAsPrimary(): void
    {
        // Remove primary flag from all other names for this person
        PersonName::where('person_id', $this->person_id)
            ->where('id', '!=', $this->id)
            ->update(['is_primary' => false]);

        $this->is_primary = true;
        $this->save();
    }

    // Relationships

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
