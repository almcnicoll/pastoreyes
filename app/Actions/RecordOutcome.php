<?php

namespace App\Actions;

use App\Models\Goal;
use App\Models\Outcome;
use Illuminate\Support\Facades\DB;

/** Record an Outcome on a Goal, linked to the Goal's people, optionally marking the Goal achieved. */
class RecordOutcome
{
    public function execute(Goal $goal, string $title, string $body, int $significance, bool $goalComplete,
                            ?string $achievedAt = null, ?string $date = null, array $source = []): Outcome
    {
        return DB::transaction(function () use ($goal, $title, $body, $significance, $goalComplete, $achievedAt, $date, $source) {
            if ($goalComplete) {
                $goal->markAchieved($achievedAt ? new \DateTime($achievedAt) : null);
            }

            $outcome = Outcome::create([
                'user_id'      => $goal->user_id,
                'goal_id'      => $goal->id,
                'title'        => $title,
                'body'         => $body,
                'significance' => $significance,
                'date'         => $date ?? now()->toDateString(),
                'logged_at'    => now(),
            ] + $source);

            // Same people as the Goal, keeping whoever is primary
            $outcome->persons()->attach(
                $goal->persons->mapWithKeys(fn($p) => [$p->id => ['is_primary' => (bool) $p->pivot->is_primary]])->all()
            );

            return $outcome;
        });
    }
}
