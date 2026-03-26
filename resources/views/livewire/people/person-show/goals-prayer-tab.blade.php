<div>

    {{-- Goals Section --}}
    <div class="mb-8">
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-base font-semibold text-gray-700 flex items-center gap-2">
                <x-entry-type-badge type="goal" />
                Goals
            </h2>
            <button wire:click="openAddGoal"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-indigo-600 text-white text-xs font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Add Goal
            </button>
        </div>

        {{-- Active Goals --}}
        <div class="space-y-2 mb-3">
            @forelse($activeGoals as $goal)
                <div class="bg-white border border-gray-100 rounded-lg px-4 py-3">
                    <div class="flex items-start gap-3">
                        <x-significance-badge :significance="$goal->significance" />
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-800">{{ $goal->title }}</p>
                            <p class="text-xs text-gray-500 mt-0.5">{{ $goal->body }}</p>
                            @if($goal->target_date)
                                <p class="text-xs text-gray-400 mt-1">
                                    Target: {{ $goal->target_date->format('j M Y') }}
                                </p>
                            @endif
                            @if($goal->outcomes->count())
                                <p class="text-xs text-gray-400 mt-0.5">
                                    {{ $goal->outcomes->count() }} {{ Str::plural('outcome', $goal->outcomes->count()) }}
                                </p>
                            @endif
                        </div>
                        <div class="flex flex-col gap-1 items-end flex-shrink-0">
                            <button wire:click="openResolveGoal({{ $goal->id }})"
                                    class="text-xs text-green-600 hover:underline">Resolve</button>
                            <button wire:click="editGoal({{ $goal->id }})"
                                    class="text-xs text-indigo-600 hover:underline">Edit</button>
                            <button wire:click="deleteGoal({{ $goal->id }})"
                                    wire:confirm="Delete this goal and all its outcomes?"
                                    class="text-xs text-red-500 hover:underline">Delete</button>
                        </div>
                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-400 py-1">No active goals.</p>
            @endforelse
        </div>

        {{-- Resolved Goals (collapsible) --}}
        @if($resolvedGoals->count())
        <div x-data="{ open: false }">
            <button @click="open = !open"
                    class="text-xs text-gray-400 hover:text-gray-600 flex items-center gap-1">
                <svg class="w-3 h-3 transition-transform" :class="open ? 'rotate-90' : ''"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
                {{ $resolvedGoals->count() }} resolved {{ Str::plural('goal', $resolvedGoals->count()) }}
            </button>
            <div x-show="open" x-collapse class="mt-2 space-y-2">
                @foreach($resolvedGoals as $goal)
                    <div class="bg-gray-50 border border-gray-100 rounded-lg px-4 py-2.5 opacity-75">
                        <div class="flex items-start gap-3">
                            <x-significance-badge :significance="$goal->significance" />
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-600 line-through">{{ $goal->title }}</p>
                                <p class="text-xs text-gray-400 mt-0.5">
                                    Achieved {{ $goal->achieved_at->format('j M Y') }}
                                </p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>

    {{-- Prayer Needs Section --}}
    <div class="mb-6">
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-base font-semibold text-gray-700 flex items-center gap-2">
                <x-entry-type-badge type="prayer_need" />
                Prayer Needs
            </h2>
            <button wire:click="openAddPrayer"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-indigo-600 text-white text-xs font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Add Prayer Need
            </button>
        </div>

        {{-- Active Prayer Needs --}}
        <div class="space-y-2 mb-3">
            @forelse($activePrayer as $need)
                <div class="bg-white border border-gray-100 rounded-lg px-4 py-3">
                    <div class="flex items-start gap-3">
                        <x-significance-badge :significance="$need->significance" />
                        <div class="flex-1 min-w-0">
                            @if($need->title)
                                <p class="text-sm font-medium text-gray-800">{{ $need->title }}</p>
                            @endif
                            <p class="text-xs text-gray-500 mt-0.5">{{ $need->body }}</p>
                            <p class="text-xs text-gray-400 mt-1">{{ $need->date->format('j M Y') }}</p>
                        </div>
                        <div class="flex flex-col gap-1 items-end flex-shrink-0">
                            <button wire:click="openResolvePrayer({{ $need->id }})"
                                    class="text-xs text-green-600 hover:underline">Resolve</button>
                            <button wire:click="editPrayer({{ $need->id }})"
                                    class="text-xs text-indigo-600 hover:underline">Edit</button>
                            <button wire:click="deletePrayer({{ $need->id }})"
                                    wire:confirm="Delete this prayer need?"
                                    class="text-xs text-red-500 hover:underline">Delete</button>
                        </div>
                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-400 py-1">No active prayer needs.</p>
            @endforelse
        </div>

        {{-- Resolved Prayer Needs (collapsible) --}}
        @if($resolvedPrayer->count())
        <div x-data="{ open: false }">
            <button @click="open = !open"
                    class="text-xs text-gray-400 hover:text-gray-600 flex items-center gap-1">
                <svg class="w-3 h-3 transition-transform" :class="open ? 'rotate-90' : ''"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
                {{ $resolvedPrayer->count() }} resolved {{ Str::plural('prayer need', $resolvedPrayer->count()) }}
            </button>
            <div x-show="open" x-collapse class="mt-2 space-y-2">
                @foreach($resolvedPrayer as $need)
                    <div class="bg-gray-50 border border-gray-100 rounded-lg px-4 py-2.5 opacity-75">
                        <div class="flex items-start gap-3">
                            <x-significance-badge :significance="$need->significance" />
                            <div class="flex-1 min-w-0">
                                @if($need->title)
                                    <p class="text-sm font-medium text-gray-600">{{ $need->title }}</p>
                                @endif
                                <p class="text-xs text-gray-400 mt-0.5">
                                    Resolved {{ $need->resolved_at->format('j M Y') }}
                                </p>
                                @if($need->resolution_details)
                                    <p class="text-xs text-gray-500 mt-0.5">{{ $need->resolution_details }}</p>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>

    {{-- Inline Form --}}
    @if($showForm)
    <div class="bg-gray-50 border border-gray-200 rounded-xl p-5">

        {{-- Goal Add/Edit Form --}}
        @if(in_array($formType, ['goal']))
        <h3 class="text-sm font-semibold text-gray-700 mb-4">{{ $editingId ? 'Edit Goal' : 'Add Goal' }}</h3>
        <form wire:submit="save" class="space-y-3">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Title</label>
                <input wire:model="title" type="text"
                       class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2"
                       placeholder="Goal summary">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Detail</label>
                <textarea wire:model="body" rows="3"
                          class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2"></textarea>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Date Set</label>
                    <input wire:model="date" type="date"
                           class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Target Date</label>
                    <input wire:model="targetDate" type="date"
                           class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2">
                </div>
            </div>
            <x-significance-picker wire:model="significance" />
            <x-form-actions wire:cancel="resetForm" />
        </form>

        {{-- Prayer Add/Edit Form --}}
        @elseif($formType === 'prayer')
        <h3 class="text-sm font-semibold text-gray-700 mb-4">{{ $editingId ? 'Edit Prayer Need' : 'Add Prayer Need' }}</h3>
        <form wire:submit="save" class="space-y-3">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Title <span class="text-gray-400">(optional)</span></label>
                <input wire:model="title" type="text"
                       class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2"
                       placeholder="Brief summary">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Detail</label>
                <textarea wire:model="body" rows="3"
                          class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2"></textarea>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Date</label>
                <input wire:model="date" type="date"
                       class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2">
            </div>
            <x-significance-picker wire:model="significance" />
            <x-form-actions wire:cancel="resetForm" />
        </form>

        {{-- Resolve Goal Form --}}
        @elseif($formType === 'resolve_goal')
        <h3 class="text-sm font-semibold text-gray-700 mb-4">Record Outcome</h3>
        <form wire:submit="save" class="space-y-3">
            <label class="flex items-center gap-2 text-sm text-gray-700">
                <input wire:model.live="goalComplete" type="checkbox" class="rounded">
                Goal complete
            </label>
            @if($goalComplete)
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Date Achieved</label>
                <input wire:model="achievedAt" type="date"
                       class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2">
            </div>
            @endif
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Outcome Title</label>
                <input wire:model="outcomeTitle" type="text"
                       class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Outcome Detail</label>
                <textarea wire:model="outcomeBody" rows="3"
                          class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2"></textarea>
            </div>
            <x-significance-picker wire:model="significance" />
            <x-form-actions wire:cancel="resetForm" />
        </form>

        {{-- Resolve Prayer Form --}}
        @elseif($formType === 'resolve_prayer')
        <h3 class="text-sm font-semibold text-gray-700 mb-4">Resolve Prayer Need</h3>
        <form wire:submit="save" class="space-y-3">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Date Resolved</label>
                <input wire:model="resolvedAt" type="date"
                       class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Resolution Details <span class="text-gray-400">(optional)</span></label>
                <textarea wire:model="resolutionDetails" rows="3"
                          class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2"
                          placeholder="How was this answered or resolved?"></textarea>
            </div>
            <x-form-actions wire:cancel="resetForm" />
        </form>
        @endif

    </div>
    @endif

</div>
