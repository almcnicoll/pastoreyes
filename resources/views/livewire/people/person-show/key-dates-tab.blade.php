<div>
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-base font-semibold text-gray-700">Key Dates</h2>
        <button wire:click="openAdd"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-indigo-600 text-white text-xs font-medium rounded-lg hover:bg-indigo-700 transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Add Date
        </button>
    </div>

    {{-- Key Dates List --}}
    <div class="space-y-2 mb-4">
        @forelse($keyDates as $kd)
            <div class="flex items-center gap-3 bg-white border border-gray-100 rounded-lg px-4 py-3">

                <x-significance-badge :significance="$kd->significance" />

                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-sm font-medium text-gray-700">
                            {{ $kd->label ?? ucfirst(str_replace('_', ' ', $kd->type)) }}
                        </span>
                        <span class="text-xs text-gray-400">
                            @if($kd->year_unknown)
                                {{ $kd->date->format('j F') }}
                            @else
                                {{ $kd->date->format('j F Y') }}
                            @endif
                        </span>
                        @if($kd->is_recurring)
                            <span class="text-xs bg-blue-50 text-blue-600 px-1.5 py-0.5 rounded">recurring</span>
                        @endif
                        @if($kd->is_synced)
                            <span class="text-xs bg-green-50 text-green-600 px-1.5 py-0.5 rounded">synced</span>
                        @else
                            <button wire:click="syncNow({{ $kd->id }})"
                                    class="text-xs bg-gray-50 text-gray-500 px-1.5 py-0.5 rounded hover:bg-gray-100 transition-colors">
                                Sync to Calendar
                            </button>
                        @endif
                    </div>
                    @if($kd->days_until !== null)
                        <p class="text-xs text-gray-400 mt-0.5">
                            @if($kd->days_until === 0)
                                Today
                            @elseif($kd->days_until === 1)
                                Tomorrow
                            @else
                                In {{ $kd->days_until }} days
                            @endif
                        </p>
                    @endif
                </div>

                <div class="flex items-center gap-2 flex-shrink-0">
                    <button wire:click="edit({{ $kd->id }})"
                            class="text-xs text-indigo-600 hover:underline">Edit</button>
                    <button wire:click="delete({{ $kd->id }})"
                            wire:confirm="Delete this key date?"
                            class="text-xs text-red-500 hover:underline">Delete</button>
                </div>

            </div>
        @empty
            <p class="text-sm text-gray-400 py-2">No key dates recorded yet.</p>
        @endforelse
    </div>

    {{-- Add/Edit Form --}}
    @if($showForm)
    <div class="bg-gray-50 border border-gray-200 rounded-xl p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-4">
            {{ $editingId ? 'Edit Key Date' : 'Add Key Date' }}
        </h3>
        <form wire:submit="save" class="space-y-3">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Type</label>
                    <select wire:model="type"
                            class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="birthday">Birthday</option>
                        <option value="wedding_anniversary">Wedding Anniversary</option>
                        <option value="bereavement">Bereavement</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Date</label>
                    <input wire:model="date" type="date"
                           class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                    <label class="flex items-center gap-2 mt-1 text-xs text-gray-500">
                        <input wire:model="yearUnknown" type="checkbox" class="rounded">
                        Year unknown
                    </label>
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Label <span class="text-gray-400">(optional)</span></label>
                <input wire:model="label" type="text"
                       class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2"
                       placeholder="e.g. Death of father John">
            </div>

            <div class="flex items-center gap-4">
                <label class="flex items-center gap-2 text-xs text-gray-600">
                    <input wire:model="isRecurring" type="checkbox" class="rounded">
                    Recurring annually
                </label>
                <label class="flex items-center gap-2 text-xs text-gray-600">
                    <input wire:model="syncToCalendar" type="checkbox" class="rounded">
                    Sync to Google Calendar
                </label>
            </div>

            @if($syncToCalendar)
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Google Calendar</label>
                <livewire:google-calendar-selector wire:model="googleCalendarId" :key="'cal-selector'" />
            </div>
            @endif

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Significance</label>
                <div class="flex items-center gap-2">
                    @foreach([1,2,3,4,5] as $s)
                        <button type="button" wire:click="$set('significance', {{ $s }})"
                                class="w-8 h-8 rounded-full text-white text-xs font-bold transition-opacity
                                    {{ $significance == $s ? 'opacity-100 ring-2 ring-offset-1 ring-gray-400' : 'opacity-50' }}"
                                style="background-color: {{ config('entry_types.significance')[$s] }}">
                            {{ $s }}
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="flex gap-3 pt-1">
                <button type="submit"
                        class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                    Save
                </button>
                <button type="button" wire:click="resetForm"
                        class="px-4 py-2 border border-gray-300 text-gray-600 text-sm rounded-lg hover:bg-gray-50">
                    Cancel
                </button>
            </div>

        </form>
    </div>
    @endif

</div>
