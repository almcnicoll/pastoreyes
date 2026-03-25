<?php

namespace App\Models;

use App\Casts\EncryptedCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Goal extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'date',
        'logged_at',
        'title',
        'body',
        'target_date',
        'achieved_at',
        'significance',
    ];

    protected function casts(): array
    {
        return [
            'title'       => EncryptedCast::class,
            'body'        => EncryptedCast::class,
            'date'        => 'date',
            'logged_at'   => 'datetime',
            'target_date' => 'date',
            'achieved_at' => 'date',
            'significance' => 'integer',
        ];
    }

    /**
     * Scope to only active (unachieved) goals.
     */
    public function scopeActive($query)
    {
        return $query->whereNull('achieved_at');
    }

    /**
     * Scope to only achieved goals.
     */
    public function scopeAchieved($query)
    {
        return $query->whereNotNull('achieved_at');
    }

    /**
     * Scope to goals with an approaching target date within a given number of days.
     */
    public function scopeApproaching($query, int $days = 30)
    {
        return $query->whereNull('achieved_at')
            ->whereNotNull('target_date')
            ->where('target_date', '<=', now()->addDays($days));
    }

    /**
     * Mark this goal as achieved, optionally setting achieved_at to a specific date.
     */
    public function markAchieved(?\DateTime $date = null): void
    {
        $this->achieved_at = $date ?? now();
        $this->save();
    }

    // Relationships

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function outcomes(): HasMany
    {
        return $this->hasMany(Outcome::class);
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
