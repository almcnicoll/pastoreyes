<div class="relative" x-data="{ open: @entangle('open') }">

    <div class="relative">
        <input
            wire:model.live.debounce.200ms="search"
            wire:focus="$set('open', true)"
            type="text"
            placeholder="Search for a person..."
            class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2 pr-8 focus:ring-indigo-500 focus:border-indigo-500"
            autocomplete="off">

        @if($value)
            <button type="button"
                    wire:click="clear"
                    class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        @endif
    </div>

    {{-- Dropdown Results --}}
    <div x-show="open"
         @click.outside="open = false"
         class="absolute z-20 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
        @foreach($results as $person)
            <button type="button"
                    wire:click="select({{ $person->id }}, '{{ addslashes($person->display_name) }}')"
                    class="w-full text-left px-3 py-2 text-sm hover:bg-indigo-50 flex items-center gap-2">
                <span style="color: {{ config('entry_types.gender_colors')[$person->gender ?? 'unknown'] }}"
                      class="font-medium">
                    {{ $person->display_name }}
                </span>
            </button>
        @endforeach
    </div>

    {{-- Hidden input carrying the actual value --}}
    <input type="hidden" wire:model="value">

</div>
