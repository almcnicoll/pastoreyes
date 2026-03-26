<div wire:init="loadCalendars">

    @if($loading)
        <div class="flex items-center gap-2 text-xs text-gray-400 py-2">
            <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
            </svg>
            Loading calendars...
        </div>

    @elseif($error)
        <p class="text-xs text-red-500 py-2">
            Could not load calendars. Check your Google connection in Settings.
        </p>

    @elseif($calendars->isEmpty())
        <p class="text-xs text-gray-400 py-2">No editable calendars found.</p>

    @else
        <select wire:model="value"
                class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
            @foreach($calendars as $cal)
                <option value="{{ $cal['id'] }}">
                    {{ $cal['summary'] }}
                    @if($cal['primary']) (primary) @endif
                </option>
            @endforeach
        </select>
    @endif

</div>
