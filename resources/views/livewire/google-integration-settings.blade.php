<div class="space-y-6" wire:init="loadCalendars">

    {{-- Connection Status --}}
    <div>
        <h3 class="text-sm font-semibold text-gray-700 mb-2">Google Account</h3>
        <div class="flex items-center gap-3 bg-green-50 border border-green-200 rounded-lg px-4 py-3">
            <div class="w-2 h-2 rounded-full bg-green-500 flex-shrink-0"></div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-green-800">Connected</p>
                <p class="text-xs text-green-600">{{ auth()->user()->email }}</p>
            </div>
            <button wire:click="disconnectGoogle"
                    wire:confirm="Disconnect your Google account? Calendar and Contacts sync will stop working."
                    class="text-xs text-red-500 hover:underline flex-shrink-0">
                Disconnect
            </button>
        </div>
        @if(auth()->user()->google_token_expires_at)
            <p class="text-xs text-gray-400 mt-1">
                Token expires {{ auth()->user()->google_token_expires_at->diffForHumans() }}
                (auto-refreshed when needed)
            </p>
        @endif
    </div>

    {{-- Default Calendar --}}
    <div>
        <h3 class="text-sm font-semibold text-gray-700 mb-2">Default Calendar</h3>
        <p class="text-xs text-gray-400 mb-3">
            New key dates will be synced to this calendar by default.
            You can override this per event.
        </p>

        @if($loadingCalendars)
            <div class="flex items-center gap-2 text-xs text-gray-400">
                <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                </svg>
                Loading your calendars...
            </div>

        @elseif($calendarsError)
            <div class="bg-red-50 border border-red-200 rounded-lg px-4 py-3">
                <p class="text-sm text-red-700">Could not load calendars from Google.</p>
                <button wire:click="loadCalendars"
                        class="mt-1 text-xs text-red-600 hover:underline">Try again</button>
            </div>

        @elseif(empty($calendars))
            <p class="text-xs text-gray-400">No editable calendars found.</p>

        @else
            <div class="flex items-center gap-3">
                <select wire:model="defaultCalendarId"
                        class="flex-1 border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                    @foreach($calendars as $cal)
                        <option value="{{ $cal['id'] }}">
                            {{ $cal['summary'] }}
                            @if($cal['primary']) (primary) @endif
                        </option>
                    @endforeach
                </select>
                <button wire:click="saveDefaultCalendar"
                        class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors whitespace-nowrap">
                    Save
                </button>
            </div>
        @endif
    </div>

    {{-- Contacts Integration Note --}}
    <div>
        <h3 class="text-sm font-semibold text-gray-700 mb-2">Google Contacts</h3>
        <p class="text-xs text-gray-500 leading-relaxed">
            People can be linked to a Google Contact from their profile page.
            When a person is linked, you can view and edit their contact details
            directly in Google Contacts. Address discrepancies are flagged
            automatically when you visit a person's profile.
        </p>
    </div>

</div>
