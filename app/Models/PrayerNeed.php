<?php

namespace App\Models;

use App\Casts\EncryptedCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class PrayerNeed extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'date',
        'logged_at',
        'title',
        'body',
        'resolved_at',
        'resolution_details',
        'significance',
    ];

    protected function casts(): array
    {
        return [
            'title'              => EncryptedCast::class,
            'body'               => EncryptedCast::class,
            'resolution_details' => EncryptedCast::class,
            'date'               => 'date',
            'logged_at'          => 'datetime',
            'resolved_at'        => 'date',
            'significance'       => 'integer',
        ];
    }

    /**
     * Scope to only unresolved prayer needs.
     */
    public function scopeUnresolved($query)
    {
        return $query->whereNull('resolved_at');
    }

    /**
     * Scope to only resolved prayer needs.
     */
    public function scopeResolved($query)
    {
        return $query->whereNotNull('resolved_at');
    }

    /**
     * Mark this prayer need as resolved.
     */
    public function resolve(string $details = null): void
    {
        $this->resolved_at = now();
        $this->resolution_details = $details;
        $this->save();
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

    public function primaryPerson(): ?Person
    {
        return $this->persons()->wherePivot('is_primary', true)->first();
    }
}
