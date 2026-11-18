<div class="relative" x-data="{ open: false }" @click.outside="open = false">

    {{-- Chosen people --}}
    @if($selected->isNotEmpty())
        <div class="flex flex-wrap gap-2 mb-2">
            @foreach($selected as $person)
                <span wire:key="chip-{{ $person->id }}"
                      class="inline-flex items-center gap-1 px-2 py-1 bg-indigo-50 text-indigo-700 text-xs rounded-full">
                    {{ $person->display_name }}
                    <button type="button" wire:click="remove({{ $person->id }})"
                            class="text-indigo-400 hover:text-indigo-600"
                            aria-label="Remove {{ $person->display_name }}">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </span>
            @endforeach
        </div>
    @endif

    {{-- Single mode shows the search box only until someone is chosen --}}
    @if($mode === 'multi' || $selected->isEmpty())
        <input wire:model.live.debounce.200ms="search"
               @focus="open = true" @input="open = true"
               type="text"
               placeholder="{{ $mode === 'multi' ? 'Search to add a person...' : $placeholder }}"
               class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500"
               autocomplete="off">

        @if($results->isNotEmpty())
            <div x-show="open" class="absolute z-20 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                @foreach($results as $person)
                    <button type="button"
                            wire:key="result-{{ $person->id }}"
                            wire:click="select({{ $person->id }})"
                            @click="open = false"
                            class="w-full text-left px-3 py-2 text-sm hover:bg-indigo-50 transition-colors">
                        <span style="color: {{ config('entry_types.gender_colors')[$person->gender ?? 'unknown'] }}"
                              class="font-medium">
                            {{ $person->display_name }}
                        </span>
                        @if($person->matched_name)
                            <span class="text-xs text-gray-400"> — matched “{{ $person->matched_name }}”</span>
                        @endif
                    </button>
                @endforeach
            </div>
        @endif
    @endif

</div>
