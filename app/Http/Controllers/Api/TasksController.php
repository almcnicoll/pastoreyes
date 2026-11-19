<?php

namespace App\Http\Controllers\Api;

use App\Actions\EntryRules;
use App\Actions\SaveTask;
use App\Models\Task;
use App\Services\Api\ApiPresenter;
use App\Services\Api\ApiResponse;
use App\Services\Api\Narrator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TasksController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->input($request, [
            'status' => 'nullable|in:active,completed,all', 'person' => 'nullable', 'due_before' => 'nullable|date',
            'due_after' => 'nullable|date', 'sort' => 'nullable|in:due,created',
            'page' => 'nullable|integer|min:1', 'per_page' => 'nullable|integer|min:1|max:100',
        ], doing: 'list tasks');

        $status = $request->query('status', 'active');
        $query  = Task::where('user_id', $this->userId())->with('persons.primaryName');

        match ($status) {
            'active'    => $query->incomplete(),
            'completed' => $query->complete(),
            default     => null,
        };

        if ($ref = $this->personRefFromQuery($request)) {
            $person = $this->person($ref, write: false);
            $query->whereHas('persons', fn($q) => $q->where('persons.id', $person->id));
        }
        $request->query('due_before') && $query->whereDate('due_date', '<=', $request->query('due_before'));
        $request->query('due_after') && $query->whereDate('due_date', '>=', $request->query('due_after'));

        $request->query('sort') === 'created' ? $query->orderByDesc('logged_at') : $query->orderBy('due_date');

        [$page, $perPage] = $this->paging($request);
        $total = (clone $query)->count();
        $tasks = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

        $this->summarise('listed tasks');

        return ApiResponse::ok(Narrator::tasks($total, $status), $tasks->map(fn($t) => ApiPresenter::task($t))->all(), $this->warnings(),
            meta: $this->pagination($total, $page, $perPage));
    }

    public function store(Request $request): JsonResponse
    {
        $input = $this->input($request, [
            'title'     => EntryRules::TITLE_REQUIRED,
            'narrative' => 'nullable|string',
            'due_date'  => 'required|date',
            'people'    => 'nullable|array|max:50',
            'people.*'  => 'required',
        ], doing: 'add that task');

        $people = $this->people($input['people'] ?? [], write: true);
        $names  = array_map(fn($p) => $p->display_name, $people);
        $about  = $names ? ' for ' . Narrator::join($names) : '';

        $message = (ApiResponse::isDryRun() ? 'Would add' : 'Added') . " the task '{$input['title']}'{$about}, due " . Narrator::date($input['due_date']) . '.';
        if (ApiResponse::isDryRun()) {
            return ApiResponse::ok($message, null, $this->warnings());
        }

        $task = (new SaveTask)->execute(null, [
            'user_id'   => $this->userId(),
            'title'     => $input['title'],
            'narrative' => $input['narrative'] ?? null,
            'due_date'  => $input['due_date'],
            'logged_at' => now(),
        ] + $this->provenance(), array_map(fn($p) => $p->id, $people));

        $this->summarise('created a task');

        return ApiResponse::created($message, ApiPresenter::task($task->load('persons.primaryName')), $this->warnings());
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $input = $this->input($request, [
            'title'     => 'sometimes|' . EntryRules::TITLE_REQUIRED,
            'narrative' => 'sometimes|nullable|string',
            'due_date'  => 'sometimes|required|date',
            'people'    => 'sometimes|array|max:50',
            'people.*'  => 'required',
        ], doing: 'update that task');

        $task   = $this->task($id);
        $people = isset($input['people']) ? $this->people($input['people'], write: true) : null;
        $fields = array_intersect_key($input, array_flip(['title', 'narrative', 'due_date']));

        $changed = array_merge(array_keys($fields), $people !== null ? ['people'] : []);
        if (ApiResponse::isDryRun()) {
            return ApiResponse::ok("Would update task {$id}: " . (Narrator::join($changed) ?: 'nothing') . '.', null, $this->warnings());
        }

        $task = (new SaveTask)->execute($task, $fields, $people !== null ? array_map(fn($p) => $p->id, $people) : null);
        $this->summarise('updated a task');

        return ApiResponse::ok("Updated the task '{$task->title}': " . (Narrator::join($changed) ?: 'nothing changed') . '.',
            ApiPresenter::task($task->load('persons.primaryName')), $this->warnings());
    }

    public function complete(Request $request, int $id): JsonResponse
    {
        return $this->setComplete($request, $id, true);
    }

    public function reopen(Request $request, int $id): JsonResponse
    {
        return $this->setComplete($request, $id, false);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->input($request, [], doing: 'delete that task');
        $task = $this->task($id);

        $people  = $task->persons->map->display_name->all();
        $warning = "Delete this task ('{$task->title}'" . ($people ? ', ' . Narrator::join($people) : '') . ')? This cannot be undone.';

        if ($response = $this->confirmDelete($request, "task_{$id}", $warning)) {
            return $response;
        }

        $task->delete();
        $this->summarise('deleted a task');

        return ApiResponse::ok('Deleted the task.');
    }

    // -------------------------------------------------------------------------

    private function task(int $id): Task
    {
        $task = Task::where('user_id', $this->userId())->with('persons.primaryName')->find($id);

        if (!$task) {
            $this->halt(ApiResponse::notFound("There is no task with id {$id}."));
        }

        return $task;
    }

    private function setComplete(Request $request, int $id, bool $complete): JsonResponse
    {
        $this->input($request, [], doing: $complete ? 'complete that task' : 'reopen that task');
        $task = $this->task($id);

        if ($task->is_complete === $complete) {
            $this->warn($complete ? 'It was already completed, so nothing changed.' : 'It was already active, so nothing changed.');
        } elseif (!ApiResponse::isDryRun()) {
            $task->update(['is_complete' => $complete]);
            $this->summarise($complete ? 'completed a task' : 'reopened a task');
        }

        $verb = ApiResponse::isDryRun() ? ($complete ? 'Would mark' : 'Would reopen') : ($complete ? 'Marked' : 'Reopened');

        return ApiResponse::ok("{$verb} the task '{$task->title}'" . ($complete ? ' as completed.' : '.'),
            ApiPresenter::task($task->fresh('persons.primaryName')), $this->warnings());
    }
}
