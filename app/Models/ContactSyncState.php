<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactSyncState extends Model
{
    protected $table = 'contact_sync_state';

    protected $fillable = [
        'user_id',
        'last_person_id',
        'last_run_at',
        'last_batch_size',
    ];

    protected function casts(): array
    {
        return [
            'last_run_at' => 'datetime',
        ];
    }

    /**
     * Get or create the sync state for a given user.
     */
    public static function forUser(User $user): self
    {
        return self::firstOrCreate(
            ['user_id' => $user->id],
            [
                'last_person_id'  => null,
                'last_run_at'     => null,
                'last_batch_size' => 0,
            ]
        );
    }

    /**
     * Advance the cursor to the last processed person ID.
     */
    public function advance(int $lastPersonId, int $batchSize): void
    {
        $this->update([
            'last_person_id'  => $lastPersonId,
            'last_run_at'     => now(),
            'last_batch_size' => $batchSize,
        ]);
    }

    /**
     * Reset the cursor to start from the beginning on the next run.
     */
    public function reset(): void
    {
        $this->update([
            'last_person_id'  => null,
            'last_run_at'     => now(),
            'last_batch_size' => 0,
        ]);
    }

    // Relationships

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}