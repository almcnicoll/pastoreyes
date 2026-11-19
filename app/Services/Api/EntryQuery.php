<?php

namespace App\Services\Api;

use App\Services\PersonSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The Timeline over the API: entries of every type, filtered, merged and paged.
 *
 * Dates and significance are plain columns, so those filters run in SQL and each type is cut to the
 * page needed before merging. Titles and bodies are encrypted, so text search (`q`) can only decrypt
 * and scan: it stops after a cap (config api.text_search_scan_cap) and says so.
 */
class EntryQuery
{
    public function __construct(private int $userId, private PersonContext $context) {}

    /**
     * @param  array  $f  person_id, types[], active, significance[], min_significance, from, to, q, sort, page, per_page
     * @return array{entries: array[], total: int, page: int, per_page: int, truncated: bool}
     */
    public function run(array $f): array
    {
        $types   = $f['types'] ?? ApiPresenter::ENTRY_TYPES;
        $page    = max(1, (int) ($f['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($f['per_page'] ?? 25)));
        $oldest  = ($f['sort'] ?? 'newest') === 'oldest';
        $words   = PersonSearch::words($f['q'] ?? '');

        $rows      = collect();
        $total     = 0;
        $truncated = false;
        $budget    = (int) config('api.text_search_scan_cap');

        foreach ($types as $type) {
            $query = $this->base($type, $f);
            $count = (clone $query)->count();

            if ($words) {
                // Decrypt-and-scan, newest first, within what is left of the scan budget
                $limit     = min($count, max($budget, 0));
                $truncated = $truncated || $count > $limit;
                $budget   -= $limit;
                $found     = $this->order($query, false)->limit($limit)->get()
                    ->filter(fn($e) => PersonSearch::hasAll(PersonSearch::norm($this->text($e, $type)), $words));
                $total    += $found->count();
            } else {
                $total += $count;
                // Key dates are few and sort specially (unknown years last), so take them all
                $found  = $type === 'key_date'
                    ? $query->get()
                    : $this->order($query, $oldest)->limit($page * $perPage)->get();
            }

            $rows = $rows->concat($found->map(fn($e) => [$type, $e]));
        }

        $sorted = $rows->sort(function ($a, $b) use ($oldest) {
            $cmp = $this->sortKey($a) <=> $this->sortKey($b);
            return $oldest ? $cmp : -$cmp;
        })->values();

        return [
            'entries'   => $sorted->slice(($page - 1) * $perPage, $perPage)
                ->map(fn($row) => ApiPresenter::entry($row[1], $row[0]))->values()->all(),
            'total'     => $total,
            'page'      => $page,
            'per_page'  => $perPage,
            'truncated' => $truncated,
        ];
    }

    private function base(string $type, array $f): Builder
    {
        $query = ApiPresenter::modelFor($type)::query()
            ->where('user_id', $this->userId)
            ->with($type === 'goal' ? ['persons.primaryName', 'outcomes'] : ['persons.primaryName']);

        if (!empty($f['person_id'])) {
            $query->whereHas('persons', fn($q) => $q->where('persons.id', $f['person_id']));
        }
        if (!empty($f['active'])) {
            match ($type) {
                'prayer_need' => $query->whereNull('resolved_at'),
                'goal'        => $query->whereNull('achieved_at'),
                default       => null,
            };
        }
        if (!empty($f['significance'])) {
            $query->whereIn('significance', (array) $f['significance']);
        }
        if (!empty($f['min_significance'])) {
            $query->where('significance', '>=', (int) $f['min_significance']);
        }
        if (!empty($f['from'])) {
            $query->whereDate('date', '>=', $f['from']);
        }
        if (!empty($f['to'])) {
            $query->whereDate('date', '<=', $f['to']);
        }

        return $query;
    }

    private function order(Builder $query, bool $oldest): Builder
    {
        return $oldest
            ? $query->orderBy('date')->orderBy('logged_at')
            : $query->orderByDesc('date')->orderByDesc('logged_at');
    }

    private function text($entry, string $type): string
    {
        return $type === 'key_date'
            ? (string) $entry->label
            : $entry->title . ' ' . $entry->body;
    }

    /** Date then logged time; a Key Date with an unknown year sorts as the oldest, as on the Timeline. */
    private function sortKey(array $row): string
    {
        [$type, $e] = $row;
        $date = $type === 'key_date' && $e->year_unknown ? '0000-00-00' : $e->date->toDateString();

        return $date . ' ' . ($e->logged_at?->format('Y-m-d H:i:s') ?? '');
    }
}
