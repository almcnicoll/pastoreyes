<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RelationshipType extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'inverse_name',
        'is_directional',
        'is_preset',
    ];

    protected function casts(): array
    {
        return [
            'is_directional' => 'boolean',
            'is_preset'      => 'boolean',
        ];
    }

    /**
     * Scope to retrieve all types available to a given user:
     * global presets (user_id = null) plus their own custom types.
     */
    public function scopeAvailableTo($query, int $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->whereNull('user_id')->orWhere('user_id', $userId);
        });
    }

    /**
     * Get the label for this relationship from a given person's perspective.
     * For directional types, returns inverse_name when viewing from the "to" end.
     */
    public function labelFromPerspective(bool $isFromEnd): string
    {
        if ($this->is_directional && !$isFromEnd && $this->inverse_name) {
            return $this->inverse_name;
        }

        return $this->name;
    }

    // Relationships

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function relationships(): HasMany
    {
        return $this->hasMany(Relationship::class);
    }
}
