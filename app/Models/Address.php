<?php

namespace App\Models;

use App\Casts\EncryptedCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Address extends Model
{
    use HasFactory;

    protected $fillable = [
        'person_id',
        'line_1',
        'line_2',
        'line_3',
        'city',
        'county',
        'postcode',
        'country',
        'date_added',
        'is_current',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'line_1'     => EncryptedCast::class,
            'line_2'     => EncryptedCast::class,
            'line_3'     => EncryptedCast::class,
            'city'       => EncryptedCast::class,
            'county'     => EncryptedCast::class,
            'postcode'   => EncryptedCast::class,
            'country'    => EncryptedCast::class,
            'date_added' => EncryptedCast::class,
            'notes'      => EncryptedCast::class,
            'is_current' => 'boolean',
        ];
    }

    /**
     * Format the address as a single string for display.
     */
    public function getFormattedAttribute(): string
    {
        $parts = array_filter([
            $this->line_1,
            $this->line_2,
            $this->line_3,
            $this->city,
            $this->county,
            $this->postcode,
            $this->country,
        ]);

        return implode(', ', $parts);
    }

    // Relationships

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
