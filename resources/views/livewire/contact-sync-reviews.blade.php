<div>

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Contact Sync Reviews</h1>
            <p class="text-sm text-gray-400 mt-0.5">
                Differences detected between PastorEyes and Google Contacts.
            </p>
        </div>

        {{-- Sync status --}}
        @if($syncState)
            <div class="text-right text-xs text-gray-400">
                @if($syncState->last_run_at)
                    <p>Last sync: {{ $syncState->last_run_at->diffForHumans() }}</p>
                    <p>Last batch: {{ $syncState->last_batch_size }} contacts</p>
                @else
                    <p>No sync run yet</p>
                @endif
            </div>
        @endif
    </div>

    {{-- Filter tabs + bulk actions --}}
    <div class="flex items-center justify-between mb-4 gap-3 flex-wrap">

        <div class="flex items-center gap-1 bg-gray-100 rounded-lg p-1">
            @foreach(['pending' => 'Pending', 'resolved' => 'Resolved', 'all' => 'All'] as $value => $label)
                <button wire:click="setFilter('{{ $value }}')"
                        class="px-3 py-1.5 text-xs font-medium rounded-md transition-colors
                            {{ $filter === $value
                                ? 'bg-white text-gray-800 shadow-sm'
                                : 'text-gray-500 hover:text-gray-700' }}">
                    {{ $label }}
                    @if($value === 'pending' && $pendingCount > 0)
                        <span class="ml-1 bg-amber-100 text-amber-700 text-xs px-1.5 py-0.5 rounded-full">
                            {{ $pendingCount }}
                        </span>
                    @endif
                </button>
            @endforeach
        </div>

        {{-- Bulk actions for pending --}}
        @if($filter === 'pending' && $pendingCount > 0)
            <div class="flex items-center gap-2" x-data="{ open: false }">
                <button @click="open = !open" @click.outside="open = false"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium border border-gray-300 rounded-lg text-gray-600 hover:bg-gray-50 transition-colors">
                    Bulk resolve
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="open"
                     class="absolute mt-8 right-6 bg-white border border-gray-200 rounded-lg shadow-lg z-10 w-52">
                    <button wire:click="resolveAll('pull')"
                            wire:confirm="Pull all Google values into PastorEyes for all {{ $pendingCount }} pending differences?"
                            @click="open = false"
                            class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-indigo-50 rounded-t-lg">
                        Pull all from Google
                    </button>
                    <button wire:click="resolveAll('ignore')"
                            wire:confirm="Ignore all {{ $pendingCount }} pending differences?"
                            @click="open = false"
                            class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-indigo-50 rounded-b-lg">
                        Ignore all
                    </button>
                </div>
            </div>
        @endif
    </div>

    {{-- Empty state --}}
    @if($reviews->isEmpty())
        <div class="text-center py-16 text-gray-400">
            @if($filter === 'pending')
                <svg class="w-10 h-10 mx-auto mb-3 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p class="text-sm font-medium text-gray-500">All caught up</p>
                <p class="text-xs mt-1">No pending differences between PastorEyes and Google Contacts.</p>
            @else
                <p class="text-sm">No reviews found.</p>
            @endif
        </div>

    {{-- Pending — grouped by person --}}
    @elseif($filter === 'pending' && $grouped)
        <div class="space-y-4">
            @foreach($grouped as $personId => $personReviews)
                @php $person = $personReviews->first()->person; @endphp
                <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">

                    {{-- Person header --}}
                    <div class="flex items-center gap-3 px-5 py-3 bg-gray-50 border-b border-gray-100">
                        <x-person-name :person="$person" size="base" />
                        <span class="text-xs text-gray-400">
                            {{ $personReviews->count() }} {{ Str::plural('difference', $personReviews->count()) }}
                        </span>
                        <a href="{{ route('people.show', $person) }}"
                           class="ml-auto text-xs text-indigo-600 hover:underline">
                            View profile →
                        </a>
                    </div>

                    {{-- Differences --}}
                    <div class="divide-y divide-gray-50">
                        @foreach($personReviews as $review)
                        <div class="px-5 py-4">
                            <div class="flex items-start gap-4">

                                {{-- Field label --}}
                                <div class="w-28 flex-shrink-0">
                                    <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                        {{ $review->field_label }}
                                    </span>
                                </div>

                                {{-- Values comparison --}}
                                <div class="flex-1 min-w-0 grid grid-cols-1 sm:grid-cols-2 gap-3">

                                    <div class="bg-blue-50 rounded-lg p-3">
                                        <p class="text-xs font-medium text-blue-600 mb-1">PastorEyes</p>
                                        <p class="text-sm text-gray-800 break-words">
                                            {{ $review->local_value ?? '(not set)' }}
                                        </p>
                                    </div>

                                    <div class="bg-green-50 rounded-lg p-3">
                                        <p class="text-xs font-medium text-green-600 mb-1">Google Contacts</p>
                                        <p class="text-sm text-gray-800 break-words">
                                            {{ $review->google_value ?? '(not set)' }}
                                        </p>
                                    </div>

                                </div>

                                {{-- Actions --}}
                                <div class="flex flex-col gap-1.5 flex-shrink-0">
                                    <button wire:click="pullToLocal({{ $review->id }})"
                                            wire:loading.attr="disabled"
                                            title="Use Google value in PastorEyes"
                                            class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium bg-green-50 text-green-700 rounded-lg hover:bg-green-100 transition-colors whitespace-nowrap">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                                        </svg>
                                        Use Google
                                    </button>

                                    @if($review->field !== 'photo')
                                    <button wire:click="pushToGoogle({{ $review->id }})"
                                            wire:loading.attr="disabled"
                                            title="Push PastorEyes value to Google"
                                            class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium bg-blue-50 text-blue-700 rounded-lg hover:bg-blue-100 transition-colors whitespace-nowrap">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M5 10l7-7m0 0l7 7m-7-7v18"/>
                                        </svg>
                                        Use PastorEyes
                                    </button>
                                    @endif

                                    <button wire:click="ignore({{ $review->id }})"
                                            title="Ignore this difference"
                                            class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium bg-gray-50 text-gray-500 rounded-lg hover:bg-gray-100 transition-colors whitespace-nowrap">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                        Ignore
                                    </button>
                                </div>

                            </div>
                        </div>
                        @endforeach
                    </div>

                </div>
            @endforeach
        </div>

    {{-- Resolved / All — flat list --}}
    @else
        <div class="bg-white border border-gray-200 rounded-xl divide-y divide-gray-50">
            @foreach($reviews as $review)
                <div class="flex items-center gap-4 px-5 py-3">

                    <div class="w-24 flex-shrink-0">
                        <x-person-name :person="$review->person" size="sm" />
                    </div>

                    <div class="w-20 flex-shrink-0">
                        <span class="text-xs text-gray-500">{{ $review->field_label }}</span>
                    </div>

                    <div class="flex-1 min-w-0 text-xs text-gray-500 truncate">
                        {{ $review->local_value ?? '—' }}
                        <span class="text-gray-300 mx-1">→</span>
                        {{ $review->google_value ?? '—' }}
                    </div>

                    <div class="flex-shrink-0">
                        @php
                            $statusConfig = [
                                'pushed_to_google' => ['bg-blue-50 text-blue-700', 'PastorEyes → Google'],
                                'pulled_to_local'  => ['bg-green-50 text-green-700', 'Google → PastorEyes'],
                                'ignored'          => ['bg-gray-100 text-gray-500', 'Ignored'],
                                'pending'          => ['bg-amber-50 text-amber-700', 'Pending'],
                            ];
                            [$cls, $label] = $statusConfig[$review->status] ?? ['bg-gray-100 text-gray-500', $review->status];
                        @endphp
                        <span class="text-xs px-2 py-0.5 rounded-full font-medium {{ $cls }}">
                            {{ $label }}
                        </span>
                    </div>

                    @if($review->resolved_at)
                        <div class="text-xs text-gray-400 flex-shrink-0">
                            {{ $review->resolved_at->format('j M Y') }}
                        </div>
                    @endif

                </div>
            @endforeach
        </div>
    @endif

</div>