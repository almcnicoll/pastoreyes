<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            CREATE OR REPLACE VIEW timeline_entries AS

            SELECT
                CONCAT('note_', id)     AS id,
                user_id,
                'note'                  AS type,
                date,
                logged_at,
                title,
                significance,
                id                      AS entryable_id,
                'App\\\\Models\\\\Note'      AS entryable_type
            FROM notes

            UNION ALL

            SELECT
                CONCAT('prayer_need_', id) AS id,
                user_id,
                'prayer_need'           AS type,
                date,
                logged_at,
                title,
                significance,
                id                      AS entryable_id,
                'App\\\\Models\\\\PrayerNeed' AS entryable_type
            FROM prayer_needs

            UNION ALL

            SELECT
                CONCAT('goal_', id)     AS id,
                user_id,
                'goal'                  AS type,
                date,
                logged_at,
                title,
                significance,
                id                      AS entryable_id,
                'App\\\\Models\\\\Goal'      AS entryable_type
            FROM goals

            UNION ALL

            SELECT
                CONCAT('outcome_', id)  AS id,
                user_id,
                'outcome'               AS type,
                date,
                logged_at,
                title,
                significance,
                id                      AS entryable_id,
                'App\\\\Models\\\\Outcome'   AS entryable_type
            FROM outcomes

            UNION ALL

            SELECT
                CONCAT('key_date_', id) AS id,
                user_id,
                'key_date'              AS type,
                date,
                logged_at,
                label                   AS title,
                significance,
                id                      AS entryable_id,
                'App\\\\Models\\\\KeyDate'   AS entryable_type
            FROM key_dates
        ");
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS timeline_entries');
    }
};
