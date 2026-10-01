<div class="max-w-lg">
    <h3 class="text-sm font-semibold text-gray-700 mb-1">Prayer Request Reminders</h3>
    <p class="text-xs text-gray-400 mb-4 leading-relaxed">
        Get an email listing the people who have outstanding prayer requests, with a link to each person's
        Goals &amp; Prayer tab. Only names and counts are emailed, never the requests themselves. People are spread
        evenly across the days you choose, to {{ auth()->user()->email }}.
    </p>

    <form wire:submit="save" class="space-y-4">
        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
            <input wire:model.live="enabled" type="checkbox" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
            Send me prayer request reminders
        </label>

        @if($enabled)
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Days</label>
                <div class="flex flex-wrap gap-x-4 gap-y-2">
                    @foreach($weekdays as $number => $name)
                        <label class="inline-flex items-center gap-1.5 text-sm text-gray-700">
                            <input wire:model.live="days" type="checkbox" value="{{ $number }}"
                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            {{ $name }}
                        </label>
                    @endforeach
                </div>
                @error('days') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Time of day</label>
                <input wire:model="time" type="time"
                       class="border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                @error('time') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>

            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                <input wire:model.live="sameTime" type="checkbox" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                Same time each day?
            </label>

            @if(!$sameTime && count($days))
                <div class="space-y-2">
                    @foreach($weekdays as $number => $name)
                        @if(in_array($number, $days))
                            <div class="flex items-center gap-3">
                                <span class="w-24 text-sm text-gray-700">{{ $name }}</span>
                                <input wire:model="times.{{ $number }}" type="time" value="{{ $times[$number] ?? $time }}"
                                       class="border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                        @endif
                    @endforeach
                </div>
            @endif

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Timezone</label>
                <select wire:model="timezone"
                        class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                    @foreach($timezones as $tz)
                        <option value="{{ $tz }}">{{ $tz }}</option>
                    @endforeach
                </select>
                @error('timezone') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>
        @endif

        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
            Save
        </button>
    </form>
</div>
