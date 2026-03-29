<div>
    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">People</h1>
        {{-- Split button: Add Person / Add from Google --}}
        <div class="hidden md:flex items-center" x-data="{ open: false }">
            <button wire:click="$dispatch('open-add-person')"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-l-lg hover:bg-indigo-700 transition-colors border-r border-indigo-500">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Add Person
            </button>
            <div class="relative">
                <button @click="open = !open" @click.outside="open = false"
                        class="inline-flex items-center px-2 py-2 bg-indigo-600 text-white text-sm font-medium rounded-r-lg hover:bg-indigo-700 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="open"
                     x-transition:enter="transition ease-out duration-100"
                     x-transition:enter-start="opacity-0 scale-95"
                     x-transition:enter-end="opacity-100 scale-100"
                     class="absolute right-0 mt-1 w-48 bg-white border border-gray-200 rounded-lg shadow-lg z-10">
                    <button wire:click="$dispatch('open-add-from-google'); open = false"
                            class="w-full flex items-center gap-2 px-4 py-2.5 text-sm text-gray-700 hover:bg-indigo-50 rounded-lg transition-colors">
                        <svg class="w-4 h-4 text-indigo-500" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                            <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                            <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                            <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                        </svg>
                        Add from Google
                    </button>
                </div>
            </div>
        </div>
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
    <div class="fixed bottom-6 right-6 md:hidden" x-data="{ open: false }">
        <div x-show="open" @click.outside="open = false"
             class="absolute bottom-16 right-0 bg-white border border-gray-200 rounded-xl shadow-lg p-2 w-52">
            <button wire:click="$dispatch('open-add-person')"
                    @click="open = false"
                    class="w-full flex items-center gap-2 px-3 py-2.5 text-sm text-gray-700 hover:bg-indigo-50 rounded-lg">
                <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Add Person
            </button>
            <button wire:click="$dispatch('open-add-from-google')"
                    @click="open = false"
                    class="w-full flex items-center gap-2 px-3 py-2.5 text-sm text-gray-700 hover:bg-indigo-50 rounded-lg">
                <svg class="w-4 h-4" viewBox="0 0 24 24">
                    <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                    <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                    <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                    <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                </svg>
                Add from Google
            </button>
        </div>
        <button @click="open = !open"
                class="w-14 h-14 rounded-full bg-indigo-600 text-white shadow-lg flex items-center justify-center hover:bg-indigo-700 transition-colors">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
        </button>
    </div>

</div>
