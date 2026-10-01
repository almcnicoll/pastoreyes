{{--
    Below xl: a magnifier icon opens a full-width bar pinned to the top of the screen (so results
    stay above the on-screen keyboard), focused immediately. At xl and up the input is always visible.
    The input is hidden with opacity/translate rather than display:none so it can be focused
    synchronously inside the tap handler, which iOS needs in order to raise the keyboard.
--}}
<div x-data="{ open: false }"
     x-on:keydown.escape.window="open = false; $refs.q.blur()"
     class="ml-auto md:ml-0 xl:relative xl:mx-3">

    {{-- Icon (below xl) --}}
    <button type="button"
            x-on:click="open = true; $refs.q.focus()"
            class="xl:hidden p-2 rounded-lg text-gray-500 hover:bg-gray-100 transition-colors"
            aria-label="Search people">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M21 21l-4.35-4.35M17 10.5a6.5 6.5 0 11-13 0 6.5 6.5 0 0113 0z"/>
        </svg>
    </button>

    {{-- Backdrop (below xl, while open) --}}
    <div x-show="open" x-on:click="open = false" class="xl:hidden fixed inset-0 z-30 bg-black/30" style="display:none"></div>

    {{-- Search panel --}}
    <div class="fixed inset-x-0 top-0 z-40 bg-white p-3 shadow-lg transition duration-150
                xl:static xl:z-auto xl:bg-transparent xl:p-0 xl:shadow-none"
         x-bind:class="open ? 'translate-y-0 opacity-100' : '-translate-y-full opacity-0 pointer-events-none xl:translate-y-0 xl:opacity-100 xl:pointer-events-auto'">

        <form wire:submit="go" class="flex items-center gap-2">
            <div class="relative flex-1">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M21 21l-4.35-4.35M17 10.5a6.5 6.5 0 11-13 0 6.5 6.5 0 0113 0z"/>
                </svg>
                <input x-ref="q"
                       wire:model.live.debounce.150ms="search"
                       x-on:keydown.arrow-down.prevent="$refs.list?.querySelector('a')?.focus()"
                       type="search"
                       enterkeyhint="go"
                       autocomplete="off" autocapitalize="off" spellcheck="false"
                       placeholder="Search people…"
                       aria-label="Search people"
                       class="w-full border border-gray-300 rounded-lg text-sm pl-9 pr-3 py-2 xl:w-56 focus:ring-indigo-500 focus:border-indigo-500">
            </div>

            {{-- Close (below xl) --}}
            <button type="button"
                    x-on:click="open = false; $wire.set('search', '')"
                    class="xl:hidden px-2 py-2 text-sm text-gray-500 hover:text-gray-800">
                Cancel
            </button>
        </form>

        {{-- Results: inline under the bar on small screens, a dropdown at xl --}}
        @if(trim($search) !== '')
            <div x-ref="list"
                 x-on:keydown.arrow-down.prevent="$event.target.closest('li')?.nextElementSibling?.querySelector('a')?.focus()"
                 x-on:keydown.arrow-up.prevent="$event.target.closest('li')?.previousElementSibling?.querySelector('a')?.focus() ?? $refs.q.focus()"
                 class="mt-2 max-h-[32vh] overflow-y-auto rounded-lg border border-gray-200 bg-white
                        xl:absolute xl:right-0 xl:z-40 xl:mt-1 xl:w-72 xl:max-h-80 xl:shadow-lg">
                @if($results->isEmpty())
                    <p class="px-3 py-3 text-sm text-gray-400">No people found.</p>
                @else
                    <ul class="divide-y divide-gray-100">
                        @foreach($results as $person)
                            <li wire:key="gs-{{ $person->id }}">
                                <a href="{{ route('people.show', $person) }}"
                                   class="flex items-center gap-2 px-3 py-3 text-sm hover:bg-indigo-50 focus:bg-indigo-50 focus:outline-none">
                                    <span class="inline-block w-2 h-2 rounded-full flex-shrink-0"
                                          style="background: {{ config('entry_types.gender_colors')[$person->gender ?? 'unknown'] }}"></span>
                                    <span class="font-medium text-gray-800 truncate">{{ $person->display_name }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif
    </div>
</div>
