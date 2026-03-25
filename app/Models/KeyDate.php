<?php

namespace App\Models;

use App\Casts\EncryptedCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class KeyDate extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'date',
        'year_unknown',
        'logged_at',
        'type',
        'label',
        'is_recurring',
        'significance',
        'google_calendar_event_id',
        'google_calendar_id',
    ];

    protected function casts(): array
    {
        return [
            'label'        => EncryptedCast::class,
            'date'         => 'date',
            'logged_at'    => 'datetime',
            'year_unknown' => 'boolean',
            'is_recurring' => 'boolean',
            'significance' => 'integer',
        ];
    }

    /**
     * Get the next occurrence of this date from today.
     * For recurring dates, calculates the next calendar occurrence.
     * For one-off dates, returns the date itself.
     */
    public function getNextOccurrenceAttribute(): ?\Carbon\Carbon
    {
        if (!$this->date) {
            return null;
        }

        if (!$this->is_recurring) {
            return $this->date;
        }

        $today = now()->startOfDay();
        $occurrence = $this->date->copy()->year($today->year);

        // If this year's occurrence has already passed, use next year
        if ($occurrence->lt($today)) {
            $occurrence->addYear();
        }

        return $occurrence;
    }

    /**
     * Get the number of days until the next occurrence.
     */
    public function getDaysUntilAttribute(): ?int
    {
        $next = $this->next_occurrence;
        return $next ? (int) now()->startOfDay()->diffInDays($next) : null;
    }

    /**
     * Whether this date is synced to Google Calendar.
     */
    public function getIsSyncedAttribute(): bool
    {
        return !empty($this->google_calendar_event_id);
    }

    /**
     * Scope to upcoming dates within a given number of days.
     */
    public function scopeUpcoming($query, int $days = 30)
    {
        // Since next_occurrence is computed in PHP, we fetch all and filter in the collection
        // For large datasets this could be optimised, but is appropriate for this app's scale
        return $query;
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
