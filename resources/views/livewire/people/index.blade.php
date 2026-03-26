<div>
    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">People</h1>
        <button wire:click="$dispatch('open-add-person')"
                class="hidden md:inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Add Person
        </button>
    </div>

    {{-- Search --}}
    <div class="mb-4">
        <div class="relative">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/>
            </svg>
            <input wire:model.live.debounce.300ms="search"
                   type="text"
                   placeholder="Search by name..."
                   class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500">
        </div>
    </div>

    {{-- Results count --}}
    <p class="text-xs text-gray-400 mb-3">
        {{ $persons->count() }} {{ Str::plural('person', $persons->count()) }} found
    </p>

    {{-- People List --}}
    @if($persons->isEmpty())
        <div class="text-center py-16 text-gray-400">
            @if($search)
                <p class="text-sm">No people found matching "{{ $search }}".</p>
            @else
                <p class="text-sm">No people added yet.</p>
                <button wire:click="$dispatch('open-add-person')"
                        class="mt-3 text-indigo-600 text-sm hover:underline">
                    Add your first person
                </button>
            @endif
        </div>
    @else
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm divide-y divide-gray-50">
            @foreach($persons as $person)
                <a href="{{ route('people.show', $person) }}"
                   class="flex items-center gap-4 px-4 py-3 hover:bg-gray-50 transition-colors">

                    {{-- Photo / Avatar --}}
                    <div class="w-10 h-10 rounded-full flex-shrink-0 overflow-hidden bg-gray-100 flex items-center justify-center">
                        @if($person->photo)
                            <img src="{{ $person->photo->data_uri }}"
                                 alt="{{ $person->display_name }}"
                                 class="w-full h-full object-cover">
                        @else
                            <span class="text-sm font-semibold"
                                  style="color: {{ config('entry_types.gender_colors')[$person->gender ?? 'unknown'] }}">
                                {{ strtoupper(substr($person->display_name, 0, 1)) }}
                            </span>
                        @endif
                    </div>

                    {{-- Name --}}
                    <div class="flex-1 min-w-0">
                        <x-person-name :person="$person" :link="false" size="base" />
                        @if($person->primaryName && $person->primaryName->spelling_uncertain)
                            <span class="text-xs text-gray-400 ml-1">(spelling uncertain)</span>
                        @endif
                    </div>

                    {{-- Chevron --}}
                    <svg class="w-4 h-4 text-gray-300 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>

                </a>
            @endforeach
        </div>
    @endif

    {{-- Mobile FAB --}}
    <div class="fixed bottom-6 right-6 md:hidden">
        <button wire:click="$dispatch('open-add-person')"
                class="w-14 h-14 rounded-full bg-indigo-600 text-white shadow-lg flex items-center justify-center hover:bg-indigo-700 transition-colors">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
        </button>
    </div>

</div>
