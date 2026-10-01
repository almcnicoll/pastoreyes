<div>
    {{-- Trigger Button (desktop, shown in Dashboard/Timeline headers) --}}
    <button wire:click="openModal"
            class="hidden md:inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Add Entry
    </button>

    {{-- Modal --}}
    @if($open)
    <div class="fixed inset-0 z-40 flex items-end sm:items-center justify-center"
         x-data
         @keydown.escape.window="$wire.closeModal()">

        {{-- Backdrop --}}
        <div class="absolute inset-0 bg-gray-900/50"
             wire:click="closeModal"></div>

        {{-- Modal Panel --}}
        <div class="relative z-50 w-full sm:max-w-lg bg-white rounded-t-2xl sm:rounded-2xl shadow-xl p-6"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 translate-y-4"
             x-transition:enter-end="opacity-100 translate-y-0">

            <div class="flex items-center justify-between mb-5">
                <h2 class="text-base font-semibold text-gray-800">Add Entry</h2>
                <button wire:click="closeModal" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <form wire:submit="save" class="space-y-4">

                {{-- People --}}
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">People</label>

                    @if(count($selectedPersonIds))
                        <div class="flex flex-wrap gap-2 mb-2">
                            @foreach($selectedPersons as $person)
                                <span class="inline-flex items-center gap-1 px-2 py-1 bg-indigo-50 text-indigo-700 text-xs rounded-full">
                                    {{ $person->display_name }}
                                    <button type="button" wire:click="removePerson({{ $person->id }})"
                                            class="text-indigo-400 hover:text-indigo-600">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </span>
                            @endforeach
                        </div>
                    @endif

                    <div class="relative">
                        <input wire:model.live.debounce.200ms="personSearch"
                               type="text"
                               placeholder="Search to add a person..."
                               class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500"
                               autocomplete="off">

                        @if($personResults && $personResults->isNotEmpty())
                            <div class="absolute z-10 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-40 overflow-y-auto">
                                @foreach($personResults as $person)
                                    <button type="button"
                                            wire:click="addPerson({{ $person->id }})"
                                            class="w-full text-left px-3 py-2 text-sm hover:bg-indigo-50 transition-colors">
                                        <span style="color: {{ config('entry_types.gender_colors')[$person->gender ?? 'unknown'] }}"
                                              class="font-medium">
                                            {{ $person->display_name }}
                                        </span>
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    @error('selectedPersonIds') <p class="text-xs text-red-600 mt-1">Select at least one person.</p> @enderror
                </div>

                {{-- Entry Type --}}
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Type</label>
                    <select wire:model.live="type"
                            class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">— Select type —</option>
                        @foreach(config('entry_types.types') as $key => $config)
                            @if($key !== 'outcome' && $key !== 'key_date')
                                <option value="{{ $key }}">{{ $config['label'] }}</option>
                            @endif
                        @endforeach
                    </select>
                </div>

                {{-- Dynamic Fields --}}
                @if($type)

                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Date</label>
                        <input wire:model="date" type="date"
                               class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>

                    @if(in_array($type, ['note', 'goal']))
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">
                            Title @if($type === 'note')<span class="text-gray-400">(optional)</span>@endif
                        </label>
                        <input wire:model="title" type="text"
                               class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500"
                               placeholder="{{ $type === 'goal' ? 'Goal summary' : 'Brief summary' }}">
                    </div>
                    @endif

                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">
                            @if($type === 'note') Note
                            @elseif($type === 'prayer_need') Prayer Need
                            @elseif($type === 'goal') Detail
                            @endif
                        </label>
                        <textarea wire:model="body" rows="3"
                                  class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                    </div>

                    @if($type === 'goal')
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Target Date <span class="text-gray-400">(optional)</span></label>
                        <input wire:model="targetDate" type="date"
                               class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    @endif

                    {{-- Significance --}}
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-2">Significance</label>
                        <div class="flex items-center gap-2">
                            @foreach([1,2,3,4,5] as $s)
                                <button type="button"
                                        wire:click="$set('significance', {{ $s }})"
                                        class="w-8 h-8 rounded-full text-white text-xs font-bold transition-opacity
                                            {{ $significance == $s ? 'opacity-100 ring-2 ring-offset-1 ring-gray-400' : 'opacity-50' }}"
                                        style="background-color: {{ config('entry_types.significance')[$s] }}">
                                    {{ $s }}
                                </button>
                            @endforeach
                        </div>
                    </div>

                @endif

                {{-- Actions --}}
                <div class="flex gap-3 pt-1">
                    <button type="submit"
                            @class(['px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors', 'opacity-50 cursor-not-allowed' => !$type])
                            @disabled(!$type)>
                        Save
                    </button>
                    <button type="button"
                            wire:click="closeModal"
                            class="px-4 py-2 border border-gray-300 text-gray-600 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                </div>

            </form>
        </div>
    </div>
    @endif
</div>
