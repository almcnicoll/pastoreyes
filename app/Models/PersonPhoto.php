<?php

namespace App\Models;

use App\Casts\EncryptedCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonPhoto extends Model
{
    use HasFactory;

    protected $fillable = [
        'person_id',
        'data',
        'mime_type',
        'file_size',
    ];

    protected $hidden = [
        'data', // Never include raw blob data in JSON serialisation by default
    ];

    protected function casts(): array
    {
        return [
            'data'      => EncryptedCast::class,
            'mime_type' => EncryptedCast::class,
        ];
    }

    /**
     * Return a data URI suitable for use in an <img> src attribute.
     */
    public function getDataUriAttribute(): string
    {
        return 'data:' . $this->mime_type . ';base64,' . $this->data;
    }

    // Relationships

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
