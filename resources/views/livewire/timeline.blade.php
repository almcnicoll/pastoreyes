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

    {{-- Mobile FAB --}}
    <div class="fixed bottom-6 right-6 md:hidden">
        <button
            x-data
            @click="Livewire.dispatch('open-quick-add', { personId: {{ $personId ?? 'null' }} })"
            class="w-14 h-14 rounded-full bg-indigo-600 text-white shadow-lg flex items-center justify-center hover:bg-indigo-700 transition-colors"
        >
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
        </button>
    </div>

</div>
