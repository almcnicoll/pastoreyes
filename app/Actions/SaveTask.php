<?php

namespace App\Actions;

use App\Models\Task;

class SaveTask
{
    /**
     * Create a task, or update one. `$personIds` null leaves a task's people as they are.
     *
     * @param  int[]|null  $personIds
     */
    public function execute(?Task $task, array $data, ?array $personIds): Task
    {
        if ($task) {
            $task->update($data);
        } else {
            $task = Task::create($data);
        }

        if ($personIds !== null) {
            $task->persons()->sync($personIds);
        }

        return $task;
    }
}
