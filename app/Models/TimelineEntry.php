<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimelineEntry extends Model
{
    /**
     * This model is backed by a database VIEW, not a table.
     * It is strictly readonly — no insert, update, or delete operations.
     */
    protected $table = 'timeline_entries';

    /**
     * The view produces composite string IDs (e.g. 'note_1').
     */
    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * No timestamps on the view itself.
     */
    public $timestamps = false;

    protected $fillable = []; // Readonly — nothing is fillable

    protected function casts(): array
    {
        return [
            'date'      => 'date',
            'logged_at' => 'datetime',
            'significance' => 'integer',
            // Note: 'title' is encrypted in source tables but the view selects
            // the raw encrypted value. Decryption is handled by loading the
            // source model via entryable_type/entryable_id when full detail is needed.
            // For list display, title is decrypted via the resolveEntryable() method below.
        ];
    }

    /**
     * Prevent any write operations on this readonly model.
     */
    public static function boot(): void
    {
        parent::boot();

        static::creating(fn() => false);
        static::updating(fn() => false);
        static::deleting(fn() => false);
    }

    /**
     * Resolve and return the underlying source model instance
     * (e.g. a Note, Goal, PrayerNeed etc.) for full detail display or editing.
     */
    public function resolveEntryable(): ?Model
    {
        if (!$this->entryable_type || !$this->entryable_id) {
            return null;
        }

        $modelClass = $this->entryable_type;

        if (!class_exists($modelClass)) {
            return null;
        }

        return $modelClass::find($this->entryable_id);
    }

    /**
     * Scope to entries belonging to a specific user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to entries of a specific type or types.
     */
    public function scopeOfType($query, string|array $type)
    {
        return $query->whereIn('type', (array) $type);
    }

    /**
     * Scope to entries at or above a minimum significance level.
     */
    public function scopeMinSignificance($query, int $min)
    {
        return $query->where('significance', '>=', $min);
    }

    /**
     * Scope to entries linked to a specific person via the person_entry join table.
     */
    public function scopeForPerson($query, int $personId)
    {
        return $query->whereExists(function ($sub) use ($personId) {
            $sub->select(\DB::raw(1))
                ->from('person_entry')
                ->whereColumn('person_entry.entryable_id', 'timeline_entries.entryable_id')
                ->whereColumn('person_entry.entryable_type', 'timeline_entries.entryable_type')
                ->where('person_entry.person_id', $personId);
        });
    }

    // Relationships

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
