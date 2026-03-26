<div>
    {{-- Person Header --}}
    <div class="flex items-center gap-4 mb-6">
        {{-- Back link --}}
        <a href="{{ route('people.index') }}"
           class="text-gray-400 hover:text-gray-600 transition-colors flex-shrink-0">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>

        {{-- Photo --}}
        <div class="w-12 h-12 rounded-full flex-shrink-0 overflow-hidden bg-gray-100 flex items-center justify-center">
            @if($person->photo)
                <img src="{{ $person->photo->data_uri }}"
                     alt="{{ $person->display_name }}"
                     class="w-full h-full object-cover">
            @else
                <span class="text-lg font-bold"
                      style="color: {{ config('entry_types.gender_colors')[$person->gender ?? 'unknown'] }}">
                    {{ strtoupper(substr($person->display_name, 0, 1)) }}
                </span>
            @endif
        </div>

        {{-- Name + subtitle --}}
        <div class="flex-1 min-w-0">
            <x-person-name :person="$person" size="xl" :link="false" />
            @if($person->primaryName && $person->primaryName->spelling_uncertain)
                <p class="text-xs text-gray-400 mt-0.5">Spelling uncertain</p>
            @endif
        </div>
    </div>

    {{-- Tabs --}}
    <div class="border-b border-gray-200 mb-6">
        <nav class="-mb-px flex gap-1 overflow-x-auto" aria-label="Person profile tabs">
            @foreach([
                'overview'      => 'Overview',
                'timeline'      => 'Timeline',
                'relationships' => 'Relationships',
                'key_dates'     => 'Key Dates',
                'goals_prayer'  => 'Goals & Prayer',
            ] as $tab => $label)
                <button wire:click="setTab('{{ $tab }}')"
                        class="whitespace-nowrap px-4 py-2 text-sm font-medium border-b-2 transition-colors
                            {{ $activeTab === $tab
                                ? 'border-indigo-600 text-indigo-600'
                                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                    {{ $label }}
                </button>
            @endforeach
        </nav>
    </div>

    {{-- Tab Panels --}}
    <div>
        @if($activeTab === 'overview')
            <livewire:people.person-show.overview-tab :person="$person" :key="'overview-'.$person->id" />
        @elseif($activeTab === 'timeline')
            <livewire:people.person-show.timeline-tab :person="$person" :key="'timeline-'.$person->id" />
        @elseif($activeTab === 'relationships')
            <livewire:people.person-show.relationships-tab :person="$person" :key="'relationships-'.$person->id" />
        @elseif($activeTab === 'key_dates')
            <livewire:people.person-show.key-dates-tab :person="$person" :key="'key-dates-'.$person->id" />
        @elseif($activeTab === 'goals_prayer')
            <livewire:people.person-show.goals-prayer-tab :person="$person" :key="'goals-prayer-'.$person->id" />
        @endif
    </div>

</div>
