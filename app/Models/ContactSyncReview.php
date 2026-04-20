<?php

namespace App\Models;

use App\Casts\EncryptedCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactSyncReview extends Model
{
    protected $fillable = [
        'user_id',
        'person_id',
        'field',
        'field_label',
        'local_value',
        'google_value',
        'status',
        'detected_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'local_value'  => EncryptedCast::class,
            'google_value' => EncryptedCast::class,
            'detected_at'  => 'datetime',
            'resolved_at'  => 'datetime',
        ];
    }

    /**
     * Scope to pending reviews only.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to resolved reviews.
     */
    public function scopeResolved($query)
    {
        return $query->whereIn('status', ['pushed_to_google', 'pulled_to_local', 'ignored']);
    }

    /**
     * Mark this review as resolved with a given resolution type.
     */
    public function resolve(string $resolution): void
    {
        $this->update([
            'status'      => $resolution,
            'resolved_at' => now(),
        ]);
    }

    /**
     * Check whether a pending review already exists for this person+field.
     * Prevents duplicate flagging on repeated sync runs.
     */
    public static function pendingExistsFor(int $personId, string $field): bool
    {
        return self::where('person_id', $personId)
            ->where('field', $field)
            ->where('status', 'pending')
            ->exists();
    }

    // Relationships

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}