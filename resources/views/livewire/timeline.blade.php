<div>
    {{-- Header (only shown on standalone Timeline page, not when embedded in Person Profile) --}}
    @unless($personId)
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Timeline</h1>
        <livewire:quick-add-entry 
            wire:key="quick-add-entry-timeline"
        />
    </div>
    @endunless

    {{-- Filters Bar --}}
    <div class="bg-white border border-gray-200 rounded-xl p-4 mb-4 space-y-3">

        {{-- Type filters --}}
        <div class="flex flex-wrap gap-2">
            @foreach($entryTypes as $key => $config)
                <button wire:click="toggleType('{{ $key }}')"
                        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium border transition-colors
                            {{ in_array($key, $filterTypes)
                                ? 'text-white border-transparent'
                                : 'bg-white text-gray-600 border-gray-200 hover:border-gray-300' }}"
                        style="{{ in_array($key, $filterTypes) ? 'background-color: ' . $config['color'] . '; border-color: ' . $config['color'] . ';' : '' }}">
                    <x-entry-type-badge :type="$key" />
                    {{ $config['label'] }}
                </button>
            @endforeach
        </div>

        {{-- Significance + Date filters --}}
        <div class="flex flex-wrap items-center gap-3">

            {{-- Significance --}}
            <div class="flex items-center gap-1">
                @foreach([1,2,3,4,5] as $s)
                    <button wire:click="toggleSignificance({{ $s }})"
                            class="w-6 h-6 rounded-full text-white text-xs font-bold transition-opacity
                                {{ in_array($s, $filterSignificance) ? 'opacity-100 ring-2 ring-offset-1 ring-gray-300' : 'opacity-40' }}"
                            style="background-color: {{ config('entry_types.significance')[$s] }}">
                        {{ $s }}
                    </button>
                @endforeach
            </div>

            {{-- Date range --}}
            <div class="flex items-center gap-2 text-xs text-gray-500">
                <input wire:model.live="dateFrom" type="date"
                       class="border border-gray-200 rounded text-xs px-2 py-1 focus:ring-indigo-500 focus:border-indigo-500">
                <span>to</span>
                <input wire:model.live="dateTo" type="date"
                       class="border border-gray-200 rounded text-xs px-2 py-1 focus:ring-indigo-500 focus:border-indigo-500">
            </div>

            {{-- Sort toggle --}}
            <button wire:click="toggleSort"
                    class="flex items-center gap-1 text-xs text-gray-500 hover:text-gray-700 transition-colors ml-auto">
                <svg class="w-3.5 h-3.5 transition-transform {{ $sortDirection === 'asc' ? 'rotate-180' : '' }}"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
                {{ $sortDirection === 'desc' ? 'Newest first' : 'Oldest first' }}
            </button>

            {{-- Clear filters --}}
            @if(!empty($filterTypes) || !empty($filterSignificance) || $dateFrom || $dateTo)
                <button wire:click="clearFilters"
                        class="text-xs text-red-400 hover:text-red-600 transition-colors">
                    Clear filters
                </button>
            @endif

        </div>
    </div>

    {{-- Quick Add (when embedded in person profile) --}}
    @if($personId)
    <div class="mb-4">
        <livewire:quick-add-entry :personId="$personId" wire:key="quick-add-entry-timeline" />
    </div>
    @endif

    {{-- Entries --}}
    <div class="space-y-2">
        @forelse($entries as $item)
            @php $entry = $item['entry']; $source = $item['source']; @endphp

            <div class="bg-white border border-gray-100 rounded-lg overflow-hidden">

                {{-- Entry Row --}}
                <button wire:click="toggleExpand('{{ $entry->id }}')"
                        class="w-full flex items-center gap-3 px-4 py-3 hover:bg-gray-50 transition-colors text-left">

                    <x-entry-type-badge :type="$entry->type" />
                    <x-significance-badge :significance="$entry->significance" />

                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap gap-x-1.5 items-baseline">
                            @foreach($source->persons as $person)
                                <x-person-name :person="$person" size="sm" />
                            @endforeach
                            @if($source->title)
                                <span class="text-xs text-gray-500 truncate">— {{ $source->title }}</span>
                            @endif
                        </div>
                        <p class="text-xs text-gray-400 mt-0.5">
                            {{ $entry->date->format('j M Y') }}
                        </p>
                    </div>

                    <svg class="w-4 h-4 text-gray-300 flex-shrink-0 transition-transform
                                {{ $expandedEntryId === $entry->id ? 'rotate-180' : '' }}"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>

                </button>

                {{-- Expanded Body --}}
                @if($expandedEntryId === $entry->id)
                <div class="px-4 pb-4 pt-1 border-t border-gray-50">
                    @if($source->body)
                        <p class="text-sm text-gray-700 whitespace-pre-wrap mb-3">{{ $source->body }}</p>
                    @endif

                    {{-- Type-specific extra fields --}}
                    @if($entry->type === 'prayer_need' && $source->resolved_at)
                        <p class="text-xs text-green-600 mb-1">
                            Resolved {{ $source->resolved_at->format('j M Y') }}
                        </p>
                        @if($source->resolution_details)
                            <p class="text-xs text-gray-500">{{ $source->resolution_details }}</p>
                        @endif
                    @endif

                    @if($entry->type === 'goal' && $source->achieved_at)
                        <p class="text-xs text-green-600">
                            Achieved {{ $source->achieved_at->format('j M Y') }}
                        </p>
                    @endif

                    {{-- Link to full record --}}
                    @if($source->persons->isNotEmpty())
                        <a href="{{ route('people.show', $source->persons->first()) }}"
                           class="mt-2 inline-block text-xs text-indigo-600 hover:underline">
                            View person profile →
                        </a>
                    @endif

                    {{-- Edit button --}}
                    <div class="mt-3 flex justify-end">
                        <button wire:click="openEdit('{{ $entry->id }}')"
                                class="text-xs text-indigo-600 hover:underline">
                            Edit
                        </button>
                    </div>
                </div>
                @endif

            </div>
        @empty
            <div class="text-center py-12 text-gray-400">
                <p class="text-sm">No entries found.</p>
                @if(!empty($filterTypes) || !empty($filterSignificance) || $dateFrom || $dateTo)
                    <button wire:click="clearFilters" class="mt-2 text-indigo-600 text-xs hover:underline">
                        Clear filters
                    </button>
                @endif
            </div>
        @endforelse
    </div>

    {{-- Edit Form Modal --}}
    @if($showEditForm)
    <div class="fixed inset-0 z-40 flex items-end sm:items-center justify-center"
         @keydown.escape.window="$wire.cancelEdit()">

        <div class="absolute inset-0 bg-gray-900/50" wire:click="cancelEdit"></div>

        <div class="relative z-50 w-full sm:max-w-lg bg-white rounded-t-2xl sm:rounded-2xl shadow-xl p-6 max-h-[90vh] overflow-y-auto"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 translate-y-4"
             x-transition:enter-end="opacity-100 translate-y-0">

            <div class="flex items-center justify-between mb-5">
                <div class="flex items-center gap-2">
                    <h2 class="text-base font-semibold text-gray-800">Edit Entry</h2>
                    <x-entry-type-badge :type="$editType" />
                </div>
                <button wire:click="cancelEdit" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <form wire:submit="saveEdit" class="space-y-4">

                {{-- Date --}}
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Date</label>
                    <input wire:model="editDate" type="date"
                           class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                    @error('editDate')
                        <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Significance --}}
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Significance</label>
                    <div class="flex items-center gap-2">
                        @foreach([1,2,3,4,5] as $s)
                            <button type="button" wire:click="$set('editSignificance', {{ $s }})"
                                    class="w-8 h-8 rounded-full text-white text-xs font-bold transition-opacity
                                        {{ $editSignificance == $s ? 'opacity-100 ring-2 ring-offset-1 ring-gray-400' : 'opacity-40' }}"
                                    style="background-color: {{ config('entry_types.significance')[$s] }}">
                                {{ $s }}
                            </button>
                        @endforeach
                    </div>
                    @error('editSignificance')
                        <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Title (note, prayer_need, goal, outcome) --}}
                @if(in_array($editType, ['note', 'prayer_need', 'goal', 'outcome']))
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">
                        Title <span class="text-gray-400">(optional)</span>
                    </label>
                    <input wire:model="editTitle" type="text"
                           class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500"
                           placeholder="Short title...">
                </div>
                @endif

                {{-- Body (note, prayer_need, goal, outcome) --}}
                @if(in_array($editType, ['note', 'prayer_need', 'goal', 'outcome']))
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Body</label>
                    <textarea wire:model="editBody" rows="4"
                              class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500"
                              placeholder="Content..."></textarea>
                    @error('editBody')
                        <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                    @enderror
                </div>
                @endif

                {{-- prayer_need: resolved_at + resolution_details --}}
                @if($editType === 'prayer_need')
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">
                        Resolved Date <span class="text-gray-400">(optional)</span>
                    </label>
                    <input wire:model="editResolvedAt" type="date"
                           class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                    @error('editResolvedAt')
                        <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                    @enderror
                </div>
                @if($editResolvedAt)
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">
                        Resolution Details <span class="text-gray-400">(optional)</span>
                    </label>
                    <textarea wire:model="editResolutionDetails" rows="2"
                              class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500"
                              placeholder="How was this resolved?"></textarea>
                </div>
                @endif
                @endif

                {{-- goal: target_date + achieved_at --}}
                @if($editType === 'goal')
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">
                        Target Date <span class="text-gray-400">(optional)</span>
                    </label>
                    <input wire:model="editTargetDate" type="date"
                           class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                    @error('editTargetDate')
                        <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">
                        Achieved Date <span class="text-gray-400">(optional)</span>
                    </label>
                    <input wire:model="editAchievedAt" type="date"
                           class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                    @error('editAchievedAt')
                        <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                    @enderror
                </div>
                @endif

                {{-- key_date: type, label, is_recurring, year_unknown --}}
                @if($editType === 'key_date')
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Date Type</label>
                    <select wire:model="editKeyDateType"
                            class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="birthday">Birthday</option>
                        <option value="wedding_anniversary">Wedding Anniversary</option>
                        <option value="bereavement">Bereavement</option>
                        <option value="other">Other</option>
                    </select>
                    @error('editKeyDateType')
                        <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">
                        Label <span class="text-gray-400">(optional)</span>
                    </label>
                    <input wire:model="editLabel" type="text"
                           class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500"
                           placeholder="Custom label...">
                    @error('editLabel')
                        <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <div class="flex items-center gap-4">
                    <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
                        <input wire:model="editIsRecurring" type="checkbox"
                               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        Recurring annually
                    </label>
                    <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
                        <input wire:model="editYearUnknown" type="checkbox"
                               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        Year unknown
                    </label>
                </div>
                @endif

                {{-- Person links --}}
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">
                        Related People <span class="text-gray-400">(optional)</span>
                    </label>

                    {{-- Selected people chips --}}
                    @if(count($editSelectedPersonIds))
                        <div class="flex flex-wrap gap-2 mb-2">
                            @foreach($editSelectedPersons as $person)
                                <span class="inline-flex items-center gap-1 px-2 py-1 bg-indigo-50 text-indigo-700 text-xs rounded-full">
                                    {{ $person->display_name }}
                                    <button type="button" wire:click="removeEditPerson({{ $person->id }})"
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
                    <div class="relative">
                        <input wire:model.live.debounce.200ms="editPersonSearch"
                               type="text"
                               placeholder="Search to add a person..."
                               class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500"
                               autocomplete="off">

                        @if($editPersonResults && $editPersonResults->isNotEmpty())
                            <div class="absolute z-10 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-40 overflow-y-auto">
                                @foreach($editPersonResults as $person)
                                    <button type="button"
                                            wire:click="addEditPerson({{ $person->id }})"
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
                    <button type="button" wire:click="cancelEdit"
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
        <button
            wire:click="$dispatch('open-quick-add', { personId: {{ $personId ?? 'null' }} })"
            class="w-14 h-14 rounded-full bg-indigo-600 text-white shadow-lg flex items-center justify-center hover:bg-indigo-700 transition-colors"
        >
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
        </button>
    </div>

</div>
