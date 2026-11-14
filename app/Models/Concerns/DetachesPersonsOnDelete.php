<?php

namespace App\Models\Concerns;

/**
 * person_entry is a polymorphic pivot with no foreign key on the entry side, so
 * deleting an entry would otherwise leave its person links behind.
 */
trait DetachesPersonsOnDelete
{
    protected static function bootDetachesPersonsOnDelete(): void
    {
        static::deleting(fn($entry) => $entry->persons()->detach());
    }
}
