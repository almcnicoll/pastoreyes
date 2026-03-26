<div>
    @if($open)
    <div class="fixed inset-0 z-40 flex items-end sm:items-center justify-center"
         @keydown.escape.window="$wire.closeModal()">

        <div class="absolute inset-0 bg-gray-900/50" wire:click="closeModal"></div>

        <div class="relative z-50 w-full sm:max-w-lg bg-white rounded-t-2xl sm:rounded-2xl shadow-xl p-6"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 translate-y-4"
             x-transition:enter-end="opacity-100 translate-y-0">

            <div class="flex items-center justify-between mb-5">
                <h2 class="text-base font-semibold text-gray-800">Find in Google Contacts</h2>
                <button wire:click="closeModal" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Search Input --}}
            <div class="relative mb-4">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z"/>
                </svg>
                <input wire:model.live.debounce.400ms="search"
                       type="text"
                       placeholder="Search Google Contacts..."
                       class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500"
                       autofocus>
            </div>

            {{-- Results --}}
            @if($loading)
                <div class="flex items-center justify-center py-8 text-gray-400 text-sm gap-2">
                    <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                    </svg>
                    Searching...
                </div>

            @elseif($error)
                <p class="text-sm text-red-500 py-4 text-center">
                    Could not search Google Contacts. Check your connection in Settings.
                </p>

            @elseif($results->isNotEmpty())
                <div class="divide-y divide-gray-50 max-h-64 overflow-y-auto">
                    @foreach($results as $contact)
                        <button wire:click="linkContact('{{ $contact['resourceName'] }}')"
                                class="w-full flex items-center gap-3 px-2 py-3 hover:bg-indigo-50 transition-colors text-left rounded-lg">

                            @if($contact['photoUrl'])
                                <img src="{{ $contact['photoUrl'] }}"
                                     alt="{{ $contact['displayName'] }}"
                                     class="w-9 h-9 rounded-full flex-shrink-0 object-cover">
                            @else
                                <div class="w-9 h-9 rounded-full bg-gray-200 flex items-center justify-center flex-shrink-0">
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
                            </div>

                            <svg class="w-4 h-4 text-indigo-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                            </svg>

                        </button>
                    @endforeach
                </div>

            @elseif(strlen($search) >= 2)
                <p class="text-sm text-gray-400 py-4 text-center">
                    No contacts found matching "{{ $search }}".
                </p>

            @else
                <p class="text-sm text-gray-400 py-4 text-center">
                    Type a name to search your Google Contacts.
                </p>
            @endif

        </div>
    </div>
    @endif
</div>
