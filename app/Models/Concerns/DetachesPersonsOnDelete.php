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
        // A braced body, so it returns nothing: a model event listener that returns a value stops the
        // listeners after it from running (a Goal's outcomes would then never be deleted)
        static::deleting(function ($entry) {
            $entry->persons()->detach();
        });
    }
}
