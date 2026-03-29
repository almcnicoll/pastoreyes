<div>
    @if($open)
    <div class="fixed inset-0 z-40 flex items-end sm:items-center justify-center"
         @keydown.escape.window="$wire.closeModal()">

        <div class="absolute inset-0 bg-gray-900/50" wire:click="closeModal"></div>

        <div class="relative z-50 w-full sm:max-w-lg bg-white rounded-t-2xl sm:rounded-2xl shadow-xl p-6"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 translate-y-4"
             x-transition:enter-end="opacity-100 translate-y-0">

            {{-- Header --}}
            <div class="flex items-center justify-between mb-5">
                <div class="flex items-center gap-2">
                    @if($preview)
                        <button wire:click="back" class="text-gray-400 hover:text-gray-600 mr-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                            </svg>
                        </button>
                    @endif
                    <h2 class="text-base font-semibold text-gray-800">
                        {{ $preview ? 'Confirm Import' : 'Add from Google Contacts' }}
                    </h2>
                </div>
                <button wire:click="closeModal" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Error --}}
            @if($error)
                <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
                    Could not connect to Google Contacts. Check your Google connection in Settings.
                </div>
            @endif

            {{-- Search screen --}}
            @if(!$preview)

                <div class="relative mb-4">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z"/>
                    </svg>
                    <input wire:model.live.debounce.400ms="search"
                           type="text"
                           placeholder="Search Google Contacts by name..."
                           class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500"
                           autofocus>
                </div>

                @if($loading)
                    <div class="flex items-center justify-center py-8 text-gray-400 text-sm gap-2">
                        <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                        </svg>
                        Searching...
                    </div>

                @elseif($results->isNotEmpty())
                    <div class="divide-y divide-gray-50 max-h-72 overflow-y-auto">
                        @foreach($results as $contact)
                            <button wire:click="selectContact('{{ $contact['resourceName'] }}')"
                                    class="w-full flex items-center gap-3 px-2 py-3 hover:bg-indigo-50 transition-colors text-left rounded-lg">

                                @if($contact['photoUrl'])
                                    <img src="{{ $contact['photoUrl'] }}"
                                         alt="{{ $contact['displayName'] }}"
                                         class="w-10 h-10 rounded-full flex-shrink-0 object-cover">
                                @else
                                    <div class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center flex-shrink-0">
                                        <span class="text-sm font-semibold text-gray-500">
                                            {{ strtoupper(substr($contact['displayName'], 0, 1)) }}
                                        </span>
                                    </div>
                                @endif

                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-800">{{ $contact['displayName'] }}</p>
                                    @if(!empty($contact['emails']))
                                        <p class="text-xs text-gray-400 truncate">{{ $contact['emails'][0] }}</p>
                                    @endif
                                    @if(!empty($contact['phones']))
                                        <p class="text-xs text-gray-400">{{ $contact['phones'][0] }}</p>
                                    @endif
                                </div>

                                <svg class="w-4 h-4 text-gray-300 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>

                            </button>
                        @endforeach
                    </div>

                @elseif(strlen($search) >= 2 && !$loading)
                    <p class="text-sm text-gray-400 py-4 text-center">
                        No contacts found matching "{{ $search }}".
                    </p>

                @else
                    <p class="text-sm text-gray-400 py-4 text-center">
                        Type at least 2 characters to search.
                    </p>
                @endif

            {{-- Preview / confirm screen --}}
            @else

                <div class="mb-5">

                    {{-- Contact header --}}
                    <div class="flex items-center gap-3 mb-4">
                        @if($preview['photoUrl'])
                            <img src="{{ $preview['photoUrl'] }}"
                                 alt="{{ $preview['displayName'] }}"
                                 class="w-12 h-12 rounded-full object-cover flex-shrink-0">
                        @else
                            <div class="w-12 h-12 rounded-full bg-gray-200 flex items-center justify-center flex-shrink-0">
                                <span class="text-lg font-bold text-gray-500">
                                    {{ strtoupper(substr($preview['displayName'], 0, 1)) }}
                                </span>
                            </div>
                        @endif
                        <div>
                            <p class="text-base font-semibold text-gray-800">{{ $preview['displayName'] }}</p>
                            <p class="text-xs text-gray-400">Will be added as a new person</p>
                        </div>
                    </div>

                    {{-- What will be imported --}}
                    @if(!empty($preview['items']))
                        <div class="bg-gray-50 rounded-lg p-4 space-y-2 mb-4">
                            <p class="text-xs font-medium text-gray-500 mb-2">The following will be imported:</p>
                            @foreach($preview['items'] as $item)
                                <div class="flex items-start gap-2 text-sm text-gray-700">
                                    <svg class="w-4 h-4 text-green-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    {{ $item['label'] }}
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="bg-gray-50 rounded-lg p-4 mb-4">
                            <p class="text-sm text-gray-500">
                                Only the name will be imported — no additional data found on this contact.
                            </p>
                        </div>
                    @endif

                    <p class="text-xs text-gray-400">
                        You can add or edit any details on the person's profile after import.
                    </p>
                </div>

                <div class="flex gap-3">
                    <button wire:click="import"
                            wire:loading.attr="disabled"
                            class="flex-1 flex items-center justify-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-60">
                        <svg wire:loading wire:target="import" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                        </svg>
                        <span wire:loading.remove wire:target="import">Import Contact</span>
                        <span wire:loading wire:target="import">Importing...</span>
                    </button>
                    <button wire:click="back"
                            class="px-4 py-2 border border-gray-300 text-gray-600 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">
                        Back
                    </button>
                </div>

            @endif

        </div>
    </div>
    @endif
</div>
