<?php

namespace App\Http\Controllers\Api;

use App\Models\ContactSyncReview;
use App\Models\Goal;
use App\Models\KeyDate;
use App\Models\PrayerNeed;
use App\Models\Task;
use App\Services\Api\ApiPresenter;
use App\Services\Api\ApiResponse;
use App\Services\Api\EntryQuery;
use App\Services\Api\Narrator;
use App\Services\PersonSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BriefingController extends ApiController
{
    /** The Dashboard: what needs attention across everyone. */
    public function index(Request $request): JsonResponse
    {
        $input = $this->input($request, ['days' => 'nullable|integer|min:1|max:365'], doing: 'build the briefing');
        $days  = (int) ($input['days'] ?? $this->token()->user->settings['upcoming_days_window'] ?? 30);
        $uid   = $this->userId();

        $keyDates = KeyDate::where('user_id', $uid)->with('persons.primaryName')->get()
            ->filter(fn($k) => $k->days_until !== null && $k->days_until >= -1 && $k->days_until <= $days)
            ->sortBy('days_until')->values();

        $needs = PrayerNeed::where('user_id', $uid)->unresolved()->with('persons.primaryName')
            ->orderByDesc('significance')->orderByDesc('date')->get();

        $tasks = Task::where('user_id', $uid)->incomplete()->where('due_date', '<=', now()->addDays($days))
            ->with('persons.primaryName')->orderBy('due_date')->get();

        $goals = Goal::where('user_id', $uid)->approaching($days)->with(['persons.primaryName', 'outcomes'])->orderBy('target_date')->get();

        $entries = new EntryQuery($uid, $this->context());
        $recent  = collect(ApiPresenter::ENTRY_TYPES)->flatMap(
            fn($type) => ApiPresenter::modelFor($type)::where('user_id', $uid)
                ->with($type === 'goal' ? ['persons.primaryName', 'outcomes'] : ['persons.primaryName'])
                ->orderByDesc('logged_at')->limit(5)->get()->map(fn($e) => [$e->logged_at, ApiPresenter::entry($e, $type)])
        )->sortByDesc(0)->take(5)->map(fn($pair) => $pair[1])->values()->all();

        $high = $entries->run(['min_significance' => 4, 'from' => now()->subDays(90)->toDateString(), 'per_page' => 5])['entries'];

        $pendingSync = ContactSyncReview::where('user_id', $uid)->where('status', 'pending')->count();

        $counts = ['key_dates' => $keyDates->count(), 'prayer_needs' => $needs->count(), 'tasks' => $tasks->count(),
                   'goals' => $goals->count(), 'sync_differences' => $pendingSync];

        return ApiResponse::ok(Narrator::briefing($counts, $days), [
            'window_days'          => $days,
            'upcoming_key_dates'   => $keyDates->map(function (KeyDate $k) {
                return ApiPresenter::keyDate($k) + ['people' => $k->persons->map(fn($p) => ['id' => $p->id, 'display_name' => $p->display_name])->all()];
            })->all(),
            'active_prayer_needs'  => $needs->take(20)->map(fn($n) => ApiPresenter::entry($n, 'prayer_need'))->all(),
            'tasks_due_or_overdue' => $tasks->map(fn($t) => ApiPresenter::task($t))->all(),
            'approaching_goals'    => $goals->map(fn($g) => ApiPresenter::entry($g, 'goal'))->all(),
            'recent_entries'       => $recent,
            'high_significance'    => $high,
            'pending_sync_differences' => $pendingSync,
        ]);
    }

    /** Text search: people always, and (v1.1 behaviour) entries by decrypting and scanning within a cap. */
    public function search(Request $request): JsonResponse
    {
        $input = $this->input($request, [
            'q'        => 'required|string|min:1|max:200',
            'scope'    => 'nullable|in:people,entries,all',
            'page'     => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ], doing: 'search');

        $scope = $input['scope'] ?? 'people';
        [$page, $perPage] = $this->paging($request);
        $data  = [];
        $meta  = [];
        $parts = [];

        if (in_array($scope, ['people', 'all'], true)) {
            $found = $this->context()->persons()
                ->map(fn($p) => PersonSearch::match($p, PersonSearch::words($input['q'])))->filter()
                ->sortBy(fn($p) => PersonSearch::norm($p->display_name))->values();

            $data['people'] = $found->slice(($page - 1) * $perPage, $perPage)
                ->map(fn($p) => ApiPresenter::summary($p, $this->context()) + ['matched_name' => $p->matched_name])->values()->all();
            $parts[] = Narrator::people($input['q'], $found->count());
        }

        if (in_array($scope, ['entries', 'all'], true)) {
            $result = (new EntryQuery($this->userId(), $this->context()))->run(['q' => $input['q'], 'page' => $page, 'per_page' => $perPage]);
            $data['entries'] = $result['entries'];
            $meta  = $this->pagination($result['total'], $page, $perPage) + ($result['truncated'] ? ['truncated' => true] : []);
            $parts[] = Narrator::entries($result['total'], count($result['entries']), [])
                . ($result['truncated'] ? ' Only the newest ' . config('api.text_search_scan_cap') . ' entries were scanned: narrow it with a person or date range for a complete search.' : '');
        }

        $this->summarise("searched {$scope}");

        return ApiResponse::ok(implode(' ', $parts), $data, meta: $meta);
    }
}
