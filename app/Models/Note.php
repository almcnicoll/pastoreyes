<?php

namespace App\Models;

use App\Casts\EncryptedCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Note extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'date',
        'logged_at',
        'title',
        'body',
        'significance',
    ];

    protected function casts(): array
    {
        return [
            'title'       => EncryptedCast::class,
            'body'        => EncryptedCast::class,
            'date'        => 'date',
            'logged_at'   => 'datetime',
            'significance' => 'integer',
        ];
    }

    // Relationships

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function persons(): MorphToMany
    {
        return $this->morphToMany(Person::class, 'entryable', 'person_entry')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    /**
     * Get the primary person this note is about.
     */
    public function primaryPerson(): ?Person
    {
        return $this->persons()->wherePivot('is_primary', true)->first();
    }
}
