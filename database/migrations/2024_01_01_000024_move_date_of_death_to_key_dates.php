<?php

use App\Casts\EncryptedCast;
use App\Models\KeyDate;
use App\Models\Person;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A recurring key date can stop after N anniversaries (null = recurs forever)
        Schema::table('key_dates', function (Blueprint $table) {
            $table->unsignedTinyInteger('recurrence_count')->nullable()->after('is_recurring');
        });

        // `type` is a MySQL ENUM: add the new value before any death key date is inserted
        $this->setTypeEnum(['birthday', 'wedding_anniversary', 'bereavement', 'death', 'other']);

        // Move each date of death into a "death" key date (read raw: Person no longer casts the column)
        $rows = DB::table('persons')->whereNotNull('date_of_death')->get(['id', 'user_id', 'date_of_death']);

        foreach ($rows as $row) {
            try {
                $owner = (new Person)->forceFill(['user_id' => $row->user_id]);
                auth()->onceUsingId($row->user_id);
                $raw = (new EncryptedCast)->get($owner, 'date_of_death', $row->date_of_death, []);
                if (!$raw) {
                    continue;
                }

                $kd = KeyDate::create([
                    'user_id'          => $row->user_id,
                    'date'             => \Carbon\Carbon::parse($raw)->format('Y-m-d'),
                    'year_unknown'     => false,
                    'logged_at'        => now(),
                    'type'             => 'death',
                    'is_recurring'     => true,
                    'recurrence_count' => 3,
                    'significance'     => 4,
                ]);
                $kd->persons()->attach($row->id, ['is_primary' => true]);
            } catch (\Throwable $e) {
                // Don't block the migration, but say which person's date wasn't moved
                Log::warning("Migration: failed to create death KeyDate for person {$row->id}: " . $e->getMessage());
            }
        }

        Schema::table('persons', function (Blueprint $table) {
            $table->dropColumn('date_of_death');
        });
    }

    public function down(): void
    {
        Schema::table('persons', function (Blueprint $table) {
            $table->text('date_of_death')->nullable()->after('gender');
        });

        Schema::table('key_dates', function (Blueprint $table) {
            $table->dropColumn('recurrence_count');
        });
        // Death key dates are left in place: converting them back would risk data loss.
        // The ENUM keeps its 'death' value for the same reason.
    }

    private function setTypeEnum(array $values): void
    {
        if (DB::getDriverName() === 'mysql' || DB::getDriverName() === 'mariadb') {
            $list = "'" . implode("','", $values) . "'";
            DB::statement("ALTER TABLE key_dates MODIFY type ENUM({$list}) NOT NULL");
        }
    }
};
