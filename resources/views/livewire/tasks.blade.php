<div>
    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Tasks</h1>
        <button wire:click="openAdd"
                class="hidden md:inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Add Task
        </button>
    </div>

    {{-- Filter + Sort Bar --}}
    <div class="flex items-center justify-between mb-4 gap-3">
        <div class="flex items-center gap-1 bg-gray-100 rounded-lg p-1">
            @foreach(['incomplete' => 'Active', 'complete' => 'Completed', 'all' => 'All'] as $value => $label)
                <button wire:click="setFilter('{{ $value }}')"
                        class="px-3 py-1.5 text-xs font-medium rounded-md transition-colors
                            {{ $filter === $value
                                ? 'bg-white text-gray-800 shadow-sm'
                                : 'text-gray-500 hover:text-gray-700' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        <button wire:click="toggleSort"
                class="flex items-center gap-1 text-xs text-gray-500 hover:text-gray-700 transition-colors">
            <svg class="w-3.5 h-3.5 transition-transform {{ $sortDirection === 'desc' ? 'rotate-180' : '' }}"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
            {{ $sortDirection === 'asc' ? 'Soonest first' : 'Latest first' }}
        </button>
    </div>

    {{-- Task List --}}
    <div class="space-y-2 mb-6">
        @forelse($tasks as $task)
            <div class="bg-white border border-gray-100 rounded-xl px-4 py-3
                {{ $task->is_complete ? 'opacity-60' : ($task->due_date->isPast() ? 'border-red-200 bg-red-50/30' : '') }}">

                <div class="flex items-start gap-3">

                    {{-- Complete checkbox --}}
                    <button wire:click="{{ $task->is_complete ? 'reopen' : 'complete' }}({{ $task->id }})"
                            class="mt-0.5 flex-shrink-0 w-5 h-5 rounded border-2 flex items-center justify-center transition-colors
                                {{ $task->is_complete
                                    ? 'bg-green-500 border-green-500'
                                    : 'border-gray-300 hover:border-indigo-400' }}">
                        @if($task->is_complete)
                            <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                            </svg>
                        @endif
                    </button>

                    <div class="flex-1 min-w-0">
                        {{-- Title --}}
                        <p class="text-sm font-medium text-gray-800 {{ $task->is_complete ? 'line-through text-gray-400' : '' }}">
                            {{ $task->title }}
                        </p>

                        {{-- Narrative --}}
                        @if($task->narrative)
                            <p class="text-xs text-gray-500 mt-0.5">{{ $task->narrative }}</p>
                        @endif

                        {{-- Meta row --}}
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-1.5">

                            {{-- Due date --}}
                            <span class="text-xs mr-2 {{ !$task->is_complete && $task->due_date->isPast() ? 'text-red-600 font-medium' : 'text-gray-400' }}">
                                @if(!$task->is_complete && $task->due_date->isToday())
                                    Due today
                                @elseif(!$task->is_complete && $task->due_date->isPast())
                                    Overdue — {{ $task->due_date->format('j M Y') }}
                                @else
                                    Due {{ $task->due_date->format('j M Y') }}
                                @endif
                            </span>

                            {{-- Linked persons --}}
                            @foreach($task->persons as $person)
                                <x-person-name :person="$person" size="sm" />
                            @endforeach

                        </div>
                    </div>

                    {{-- Actions --}}
                    <div class="flex items-center gap-2 flex-shrink-0">
                        <button wire:click="edit({{ $task->id }})"
                                class="text-xs text-indigo-600 hover:underline">Edit</button>
                        <button wire:click="delete({{ $task->id }})"
                                wire:confirm="Delete this task?"
                                class="text-xs text-red-500 hover:underline">Delete</button>
                    </div>

                </div>
            </div>
        @empty
            <div class="text-center py-12 text-gray-400">
                <p class="text-sm">
                    @if($filter === 'complete') No completed tasks.
                    @elseif($filter === 'incomplete') No active tasks.
                    @else No tasks yet.
                    @endif
                </p>
            </div>
        @endforelse
    </div>

    {{-- Add/Edit Form --}}
    @if($showForm)
    <div class="fixed inset-0 z-40 flex items-end sm:items-center justify-center"
         @keydown.escape.window="$wire.resetForm()">

        <div class="absolute inset-0 bg-gray-900/50" wire:click="resetForm"></div>

        <div class="relative z-50 w-full sm:max-w-lg bg-white rounded-t-2xl sm:rounded-2xl shadow-xl p-6"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 translate-y-4"
             x-transition:enter-end="opacity-100 translate-y-0">

            <div class="flex items-center justify-between mb-5">
                <h2 class="text-base font-semibold text-gray-800">
                    {{ $editingId ? 'Edit Task' : 'Add Task' }}
                </h2>
                <button wire:click="resetForm" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <form wire:submit="save" class="space-y-4">

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Title</label>
                    <input wire:model="title" type="text" autofocus
                           class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500"
                           placeholder="What needs to be done?">
                    @error('title')
                        <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">
                        Narrative <span class="text-gray-400">(optional)</span>
                    </label>
                    <textarea wire:model="narrative" rows="3"
                              class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500"
                              placeholder="Additional context or details..."></textarea>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Due Date</label>
                    <input wire:model="dueDate" type="date"
                           class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                    @error('dueDate')
                        <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Person links --}}
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">
                        Related People <span class="text-gray-400">(optional)</span>
                    </label>

                    {{-- Selected people chips --}}
                    @if(count($selectedPersonIds))
                        <div class="flex flex-wrap gap-2 mb-2">
                            @foreach($selectedPersons as $person)
                                <span class="inline-flex items-center gap-1 px-2 py-1 bg-indigo-50 text-indigo-700 text-xs rounded-full">
                                    {{ $person->display_name }}
                                    <button type="button" wire:click="removePerson({{ $person->id }})"
                                            class="text-indigo-400 hover:text-indigo-600">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </span>
                            @endforeach
                        </div>
                    @endif

                    {{-- Person search --}}
                    <div class="relative" x-data="{ open: @entangle('personResults').defer }">
                        <input wire:model.live.debounce.200ms="personSearch"
                               type="text"
                               placeholder="Search to add a person..."
                               class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500"
                               autocomplete="off">

                        @if($personResults->isNotEmpty())
                            <div class="absolute z-10 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-40 overflow-y-auto">
                                @foreach($personResults as $person)
                                    <button type="button"
                                            wire:click="addPerson({{ $person->id }})"
                                            class="w-full text-left px-3 py-2 text-sm hover:bg-indigo-50 transition-colors">
                                        <span style="color: {{ config('entry_types.gender_colors')[$person->gender ?? 'unknown'] }}"
                                              class="font-medium">
                                            {{ $person->display_name }}
                                        </span>
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                <div class="flex gap-3 pt-1">
                    <button type="submit"
                            class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                        Save
                    </button>
                    <button type="button" wire:click="resetForm"
                            class="px-4 py-2 border border-gray-300 text-gray-600 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                </div>

            </form>
        </div>
    </div>
    @endif

    {{-- Mobile FAB --}}
    <div class="fixed bottom-6 right-6 md:hidden">
        <button wire:click="openAdd"
                class="w-14 h-14 rounded-full bg-indigo-600 text-white shadow-lg flex items-center justify-center hover:bg-indigo-700 transition-colors">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
        </button>
    </div>

</div>
