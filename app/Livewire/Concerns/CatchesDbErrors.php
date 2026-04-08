<?php

namespace App\Livewire\Concerns;

use Illuminate\Database\QueryException;

trait CatchesDbErrors
{
    public function exceptionCatchesDbErrors($e, $stopPropagation): void
    {
        if ($e instanceof QueryException) {
            $stopPropagation();

            // Surface only the database error message, not the full stack trace.
            // getPrevious() is the underlying PDOException; fall back to the
            // QueryException message which already contains the SQLSTATE detail.
            $pdoMessage = $e->getPrevious()?->getMessage() ?? $e->getMessage();

            $this->dispatch('db-save-error', detail: $pdoMessage);
        }
    }
}
