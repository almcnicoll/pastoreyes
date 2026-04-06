<?php

namespace App\Models;

use App\Casts\EncryptedCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'narrative',
        'due_date',
        'is_complete',
        'logged_at',
    ];

    protected function casts(): array
    {
        return [
            'title'       => EncryptedCast::class,
            'narrative'   => EncryptedCast::class,
            'due_date'    => 'date',
            'logged_at'   => 'datetime',
            'is_complete' => 'boolean',
        ];
    }

    /**
     * Scope to incomplete tasks.
     */
    public function scopeIncomplete($query)
    {
        return $query->where('is_complete', false);
    }

    /**
     * Scope to complete tasks.
     */
    public function scopeComplete($query)
    {
        return $query->where('is_complete', true);
    }

    /**
     * Scope to tasks due within a given number of days.
     */
    public function scopeDueWithin($query, int $days = 30)
    {
        return $query->where('due_date', '<=', now()->addDays($days))
                     ->where('due_date', '>=', now()->startOfDay());
    }

    /**
     * Scope to overdue incomplete tasks.
     */
    public function scopeOverdue($query)
    {
        return $query->where('is_complete', false)
                     ->where('due_date', '<', now()->startOfDay());
    }

    // Relationships

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function persons(): BelongsToMany
    {
        return $this->belongsToMany(Person::class, 'person_task')
            ->withTimestamps();
    }
}
