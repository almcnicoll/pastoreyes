<?php

namespace App\Livewire;

use App\Livewire\Concerns\CatchesDbErrors;
use App\Models\Task;
use Illuminate\Support\Collection;
use Livewire\Component;

class Tasks extends Component
{
    use CatchesDbErrors;
    // Filters
    public string $filter = 'incomplete'; // incomplete, complete, all
    public string $sortDirection = 'asc'; // soonest first by default

    // Form
    public bool $showForm = false;
    public ?int $editingId = null;
    public string $title = '';
    public string $narrative = '';
    public string $dueDate = '';
    public array $selectedPersonIds = [];

    public function mount(): void
    {
        $this->dueDate = now()->addDay()->format('Y-m-d');
    }

    public function openAdd(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $task = Task::findOrFail($id);
        abort_if($task->user_id !== auth()->id(), 403);

        $this->editingId         = $id;
        $this->title             = $task->title ?? '';
        $this->narrative         = $task->narrative ?? '';
        $this->dueDate           = $task->due_date->format('Y-m-d');
        $this->selectedPersonIds = $task->persons->pluck('id')->toArray();
        $this->showForm          = true;
    }

    public function save(): void
    {
        $this->validate([
            'title'   => 'required|string|max:500',
            'dueDate' => 'required|date',
        ]);

        $data = [
            'user_id'   => auth()->id(),
            'title'     => $this->title,
            'narrative' => $this->narrative ?: null,
            'due_date'  => $this->dueDate,
            'logged_at' => now(),
        ];

        if ($this->editingId) {
            $task = Task::findOrFail($this->editingId);
            abort_if($task->user_id !== auth()->id(), 403);
            $task->update($data);
        } else {
            $task = Task::create($data);
        }

        $task->persons()->sync($this->selectedPersonIds);

        $verb = $this->editingId ? 'updated' : 'added';
        $this->resetForm();
        $this->dispatch('notify', message: "Task {$verb}.");
    }

    public function complete(int $id): void
    {
        $task = Task::findOrFail($id);
        abort_if($task->user_id !== auth()->id(), 403);
        $task->update(['is_complete' => true]);
        $this->dispatch('notify', message: 'Task marked as complete.');
    }

    public function reopen(int $id): void
    {
        $task = Task::findOrFail($id);
        abort_if($task->user_id !== auth()->id(), 403);
        $task->update(['is_complete' => false]);
        $this->dispatch('notify', message: 'Task reopened.');
    }

    public function delete(int $id): void
    {
        $task = Task::findOrFail($id);
        abort_if($task->user_id !== auth()->id(), 403);
        $task->delete();
        $this->dispatch('notify', message: 'Task deleted.');
    }

    public function toggleSort(): void
    {
        $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
    }

    public function setFilter(string $filter): void
    {
        $this->filter = $filter;
    }

    public function resetForm(): void
    {
        $this->showForm          = false;
        $this->editingId         = null;
        $this->title             = '';
        $this->narrative         = '';
        $this->dueDate           = now()->addDay()->format('Y-m-d');
        $this->selectedPersonIds = [];
    }

    public function render()
    {
        $query = Task::where('user_id', auth()->id())
            ->with('persons.primaryName');

        $query = match($this->filter) {
            'complete'   => $query->complete(),
            'incomplete' => $query->incomplete(),
            default      => $query,
        };

        $tasks = $query->orderBy('due_date', $this->sortDirection)->get();

        return view('livewire.tasks', compact('tasks'))
            ->layout('layouts.app', ['title' => 'Tasks — PastorEyes']);
    }
}
