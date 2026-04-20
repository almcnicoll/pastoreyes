<div>
    {{-- Page Header --}}
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Dashboard</h1>
        <livewire:quick-add-entry />
    </div>

    {{-- Card Grid --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

        {{-- Upcoming Key Dates --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <h2 class="text-base font-semibold text-gray-700 mb-4 flex items-center gap-2">
                <x-entry-type-badge type="key_date" />
                Upcoming Key Dates
                <span class="ml-auto text-xs text-gray-400 font-normal">Next {{ $upcomingDaysWindow }} days</span>
            </h2>

            @forelse($upcomingKeyDates as $keyDate)
                <div class="flex items-center gap-3 py-2 border-b border-gray-50 last:border-0">
                    <x-significance-badge :significance="$keyDate->significance" />
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap gap-x-1">
                            @foreach($keyDate->persons as $person)
                                <x-person-name :person="$person" size="sm" />
                            @endforeach
                        </div>
                        <p class="text-xs text-gray-500 mt-0.5">
                            {{ $keyDate->label ?? ucfirst(str_replace('_', ' ', $keyDate->type)) }}
                        </p>
                    </div>
                    <span class="text-xs text-gray-400 whitespace-nowrap">
                        @if($keyDate->days_until === 0)
                            Today
                        @elseif($keyDate->days_until === 1)
                            Tomorrow
                        @else
                            {{ $keyDate->days_until }}d
                        @endif
                    </span>
                </div>
            @empty
                <p class="text-sm text-gray-400 py-2">No upcoming dates in the next {{ $upcomingDaysWindow }} days.</p>
            @endforelse
        </div>

        {{-- Unresolved Prayer Needs --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <h2 class="text-base font-semibold text-gray-700 mb-4 flex items-center gap-2">
                <x-entry-type-badge type="prayer_need" />
                Unresolved Prayer Needs
            </h2>

            @forelse($unresolvedPrayerNeeds as $need)
                <div class="flex items-start gap-3 py-2 border-b border-gray-50 last:border-0">
                    <x-significance-badge :significance="$need->significance" />
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap gap-x-1">
                            @foreach($need->persons as $person)
                                <x-person-name :person="$person" size="sm" />
                            @endforeach
                        </div>
                        <p class="text-xs text-gray-600 mt-0.5 truncate">{{ $need->title }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">{{ $need->date->format('j M Y') }}</p>
                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-400 py-2">No unresolved prayer needs.</p>
            @endforelse

            @if($unresolvedPrayerNeeds->count() >= 5)
                <a href="{{ route('timeline') }}?type=prayer_need&resolved=0"
                   class="mt-3 block text-xs text-indigo-600 hover:underline">
                    View all →
                </a>
            @endif
        </div>

        {{-- Upcoming Tasks --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <h2 class="text-base font-semibold text-gray-700 mb-4 flex items-center gap-2">
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                </svg>
                Upcoming Tasks
                <span class="ml-auto text-xs text-gray-400 font-normal">Next {{ $upcomingDaysWindow }} days</span>
            </h2>

            @forelse($upcomingTasks as $task)
                <div class="flex items-start gap-3 py-2 border-b border-gray-50 last:border-0">
                    <span class="{{ $task->due_date->isPast() ? 'text-red-500' : 'text-gray-400' }} text-xs whitespace-nowrap mt-0.5">
                        @if($task->due_date->isToday())
                            Today
                        @elseif($task->due_date->isPast())
                            Overdue
                        @else
                            {{ $task->due_date->format('j M') }}
                        @endif
                    </span>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm text-gray-700 truncate">{{ $task->title }}</p>
                        @if($task->persons->isNotEmpty())
                            <div class="flex flex-wrap gap-x-1 mt-0.5">
                                @foreach($task->persons as $person)
                                    <x-person-name :person="$person" size="sm" />
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-400 py-2">No tasks due in the next {{ $upcomingDaysWindow }} days.</p>
            @endforelse

            @if($upcomingTasks->isNotEmpty())
                <a href="{{ route('tasks') }}"
                   class="mt-3 block text-xs text-indigo-600 hover:underline">
                    View all tasks →
                </a>
            @endif
        </div>

        {{-- Contact Sync Reviews --}}
        @php $pendingSyncCount = \App\Models\ContactSyncReview::where('user_id', auth()->id())->where('status', 'pending')->count(); @endphp
        @if($pendingSyncCount > 0)
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-5 md:col-span-2">
            <h2 class="text-base font-semibold text-amber-800 mb-2 flex items-center gap-2">
                <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 9v2m0 4h.01M12 3a9 9 0 100 18A9 9 0 0012 3z"/>
                </svg>
                Contact Sync
            </h2>
            <p class="text-sm text-amber-700">
                {{ $pendingSyncCount }} {{ Str::plural('difference', $pendingSyncCount) }} detected
                between PastorEyes and Google Contacts.
            </p>
            <a href="{{ route('contact-sync') }}"
               class="mt-3 inline-block text-sm font-medium text-amber-800 hover:underline">
                Review differences →
            </a>
        </div>
        @endif

        {{-- Approaching Goal Targets --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <h2 class="text-base font-semibold text-gray-700 mb-4 flex items-center gap-2">
                <x-entry-type-badge type="goal" />
                Approaching Goal Targets
            </h2>

            @forelse($approachingGoals as $goal)
                <div class="flex items-start gap-3 py-2 border-b border-gray-50 last:border-0">
                    <x-significance-badge :significance="$goal->significance" />
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap gap-x-1">
                            @foreach($goal->persons as $person)
                                <x-person-name :person="$person" size="sm" />
                            @endforeach
                        </div>
                        <p class="text-xs text-gray-600 mt-0.5 truncate">{{ $goal->title }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">
                            Target: {{ $goal->target_date->format('j M Y') }}
                        </p>
                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-400 py-2">No goals with approaching target dates.</p>
            @endforelse
        </div>

        {{-- Recent Activity --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <h2 class="text-base font-semibold text-gray-700 mb-4">Recent Activity</h2>

            @forelse($recentActivity as $item)
                @php $source = $item['source']; $entry = $item['entry']; @endphp
                <div class="flex items-start gap-3 py-2 border-b border-gray-50 last:border-0">
                    <x-entry-type-badge :type="$entry->type" />
                    <x-significance-badge :significance="$entry->significance" />
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap gap-x-1">
                            @foreach($source->persons as $person)
                                <x-person-name :person="$person" size="sm" />
                            @endforeach
                        </div>
                        <p class="text-xs text-gray-600 mt-0.5 truncate">{{ $source->title }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">
                            {{ $entry->logged_at->diffForHumans() }}
                        </p>
                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-400 py-2">No recent activity.</p>
            @endforelse
        </div>

        {{-- High Significance Entries --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 md:col-span-2">
            <h2 class="text-base font-semibold text-gray-700 mb-4">
                High Significance — Last 90 Days
            </h2>

            @forelse($highSignificance as $item)
                @php $source = $item['source']; $entry = $item['entry']; @endphp
                <div class="flex items-start gap-3 py-2 border-b border-gray-50 last:border-0">
                    <x-entry-type-badge :type="$entry->type" />
                    <x-significance-badge :significance="$entry->significance" />
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap gap-x-1">
                            @foreach($source->persons as $person)
                                <x-person-name :person="$person" size="sm" />
                            @endforeach
                        </div>
                        <p class="text-xs text-gray-600 mt-0.5 truncate">{{ $source->title }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">{{ $entry->date->format('j M Y') }}</p>
                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-400 py-2">No high significance entries in the last 90 days.</p>
            @endforelse
        </div>

    </div>

    {{-- Mobile FAB --}}
    <div class="fixed bottom-6 right-6 md:hidden">
        <button wire:click="$dispatch('open-quick-add')"
                class="w-14 h-14 rounded-full bg-indigo-600 text-white shadow-lg flex items-center justify-center hover:bg-indigo-700 transition-colors">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
        </button>
    </div>
</div>