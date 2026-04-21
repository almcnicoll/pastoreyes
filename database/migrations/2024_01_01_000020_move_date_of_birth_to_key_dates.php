<?php

use App\Models\KeyDate;
use App\Models\Person;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ----------------------------------------------------------------
        // Step 1: For every person with a date_of_birth but no birthday
        // KeyDate, create a KeyDate record before we drop the column.
        // ----------------------------------------------------------------
        // We can't use the encrypted cast here since we're in a migration,
        // so we use the EncryptedCast directly via the model layer.
        // We must set up auth context manually for the encryption to work,
        // so instead we read the raw encrypted value and decrypt it using
        // the same logic as EncryptedCast — but simpler: we just use the
        // model to load each person so the cast fires automatically.

        // Get all users who have persons with date_of_birth set
        $personIds = DB::table('persons')
            ->whereNotNull('date_of_birth')
            ->pluck('id');

        foreach ($personIds as $personId) {
            // Check if this person already has a birthday KeyDate
            $hasBirthday = DB::table('key_dates')
                ->where('type', 'birthday')
                ->whereExists(function ($q) use ($personId) {
                    $q->select(DB::raw(1))
                      ->from('person_entry')
                      ->whereColumn('person_entry.entryable_id', 'key_dates.id')
                      ->where('person_entry.entryable_type', 'App\\Models\\KeyDate')
                      ->where('person_entry.person_id', $personId);
                })
                ->exists();

            if ($hasBirthday) {
                continue;
            }

            // Load the person via the model so encrypted casts fire
            $person = Person::find($personId);
            if (!$person || !$person->date_of_birth) {
                continue;
            }

            // We need an authenticated user context for the encryption cast.
            // Use the person's owner.
            auth()->onceUsingId($person->user_id);

            try {
                $dateValue = \Carbon\Carbon::parse($person->date_of_birth)->format('Y-m-d');

                $kd = \App\Models\KeyDate::create([
                    'user_id'      => $person->user_id,
                    'date'         => $dateValue,
                    'year_unknown' => $person->dob_year_unknown ?? false,
                    'logged_at'    => now(),
                    'type'         => 'birthday',
                    'label'        => null,
                    'is_recurring' => true,
                    'significance' => 3,
                ]);

                DB::table('person_entry')->insert([
                    'person_id'      => $person->id,
                    'entryable_id'   => $kd->id,
                    'entryable_type' => 'App\\Models\\KeyDate',
                    'is_primary'     => true,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);

            } catch (\Exception $e) {
                // Log and continue — don't block the migration
                \Illuminate\Support\Facades\Log::warning(
                    "Migration: failed to create birthday KeyDate for person {$personId}: " . $e->getMessage()
                );
            }
        }

        // ----------------------------------------------------------------
        // Step 2: Drop the date_of_birth and dob_year_unknown columns
        // ----------------------------------------------------------------
        Schema::table('persons', function (Blueprint $table) {
            $table->dropColumn(['date_of_birth', 'dob_year_unknown']);
        });
    }

    public function down(): void
    {
        Schema::table('persons', function (Blueprint $table) {
            $table->text('date_of_birth')->nullable()->after('gender');
            $table->boolean('dob_year_unknown')->default(false)->after('date_of_birth');
        });

        // Note: down() does not attempt to reverse the KeyDate creation —
        // that would risk data loss. The columns are restored empty.
    }
};
