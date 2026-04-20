<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'PastorEyes' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-gray-50 text-gray-800">

    {{-- Navigation Bar --}}
    <nav class="bg-white border-b border-gray-200 px-4 lg:px-6 py-3" x-data="{ open: false }">
        <div class="max-w-7xl mx-auto flex items-center justify-between">

            {{-- Logo / App Name --}}
            <a href="{{ route('dashboard') }}" class="flex items-center gap-2">
                <span class="text-xl font-bold text-gray-800 tracking-tight">PastorEyes</span>
            </a>

            {{-- Desktop Navigation --}}
            <div class="hidden md:flex items-center gap-1">
                <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                    Dashboard
                </x-nav-link>
                <x-nav-link :href="route('people.index')" :active="request()->routeIs('people.*')">
                    People
                </x-nav-link>
                <x-nav-link :href="route('timeline')" :active="request()->routeIs('timeline')">
                    Timeline
                </x-nav-link>
                <x-nav-link :href="route('tasks')" :active="request()->routeIs('tasks')">
                    Tasks
                </x-nav-link>
                <x-nav-link :href="route('contact-sync')" :active="request()->routeIs('contact-sync')">
                    @php $pendingSyncCount = auth()->user() ? \App\Models\ContactSyncReview::where('user_id', auth()->id())->where('status', 'pending')->count() : 0; @endphp
                    Contact Sync
                    @if($pendingSyncCount > 0)
                        <span class="ml-1 bg-amber-400 text-white text-xs px-1.5 py-0.5 rounded-full leading-none">
                            {{ $pendingSyncCount }}
                        </span>
                    @endif
                </x-nav-link>
                <x-nav-link :href="route('settings')" :active="request()->routeIs('settings')">
                    Settings
                </x-nav-link>
            </div>

            {{-- Desktop User Menu --}}
            <div class="hidden md:flex items-center gap-3">
                <span class="text-sm text-gray-500">
                    {{ auth()->user()->first_name }}
                </span>
                @if(auth()->user()->is_admin)
                    <span class="text-xs font-medium bg-indigo-100 text-indigo-700 px-2 py-0.5 rounded-full">
                        Administrator
                    </span>
                @endif
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                            class="text-sm text-gray-500 hover:text-gray-800 transition-colors">
                        Sign out
                    </button>
                </form>
            </div>

            {{-- Mobile Hamburger --}}
            <button @click="open = !open"
                    class="md:hidden p-2 rounded-lg text-gray-500 hover:bg-gray-100 transition-colors"
                    aria-label="Toggle menu">
                <svg x-show="!open" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
                <svg x-show="open" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>

        </div>

        {{-- Mobile Menu --}}
        <div x-show="open"
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0 -translate-y-1"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-100"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 -translate-y-1"
             class="md:hidden mt-3 border-t border-gray-100 pt-3 pb-1">

            <div class="flex flex-col gap-1">
                <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" mobile>
                    Dashboard
                </x-nav-link>
                <x-nav-link :href="route('people.index')" :active="request()->routeIs('people.*')" mobile>
                    People
                </x-nav-link>
                <x-nav-link :href="route('timeline')" :active="request()->routeIs('timeline')" mobile>
                    Timeline
                </x-nav-link>
                <x-nav-link :href="route('tasks')" :active="request()->routeIs('tasks')" mobile>
                    Tasks
                </x-nav-link>
                <x-nav-link :href="route('contact-sync')" :active="request()->routeIs('contact-sync')" mobile>
                    Contact Sync
                    @if(isset($pendingSyncCount) && $pendingSyncCount > 0)
                        <span class="ml-1 bg-amber-400 text-white text-xs px-1.5 py-0.5 rounded-full leading-none">
                            {{ $pendingSyncCount }}
                        </span>
                    @endif
                </x-nav-link>
                <x-nav-link :href="route('settings')" :active="request()->routeIs('settings')" mobile>
                    Settings
                </x-nav-link>
            </div>

            <div class="mt-3 pt-3 border-t border-gray-100 flex items-center justify-between px-2">
                <div class="flex items-center gap-2">
                    <span class="text-sm text-gray-600">
                        {{ auth()->user()->first_name }} {{ auth()->user()->last_name }}
                    </span>
                    @if(auth()->user()->is_admin)
                        <span class="text-xs font-medium bg-indigo-100 text-indigo-700 px-2 py-0.5 rounded-full">
                            Administrator
                        </span>
                    @endif
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-sm text-gray-500 hover:text-gray-800">
                        Sign out
                    </button>
                </form>
            </div>

        </div>
    </nav>

    {{-- Page Content --}}
    <main class="max-w-7xl mx-auto px-4 lg:px-6 py-6">
        {{ $slot }}
    </main>

    {{-- Global: Link Google Contact modal (can be triggered from any page) --}}
    <livewire:link-google-contact />

    {{-- Global: Add Person modal (can be triggered from any page) --}}
    <livewire:people.add-person />

    {{-- Global: Add Person from Google modal --}}
    <livewire:people.add-person-from-google />

    {{-- Toast Notifications --}}
    <div
        x-data="{ show: false, message: '' }"
        x-on:notify.window="message = $event.detail.message; show = true; setTimeout(() => show = false, 3000)"
        x-show="show"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 translate-y-2"
        class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50 bg-gray-800 text-white text-sm px-4 py-2.5 rounded-lg shadow-lg"
        style="display: none;">
        <span x-text="message"></span>
    </div>
    
    {{-- DB Error Toast --}}
    <div
        x-data="{ show: false, detail: '', showModal: false }"
        x-on:db-save-error.window="detail = $event.detail.detail; show = true"
        x-show="show"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 translate-y-2"
        class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50 flex items-center gap-3 bg-red-700 text-white text-sm px-4 py-2.5 rounded-lg shadow-lg"
        style="display: none;">
        <span>Database save failed.</span>
        <button
            type="button"
            x-on:click="showModal = true"
            class="underline underline-offset-2 whitespace-nowrap hover:text-red-200 focus:outline-none">
            More info
        </button>
        <button
            type="button"
            x-on:click="show = false"
            class="ml-1 text-red-200 hover:text-white focus:outline-none"
            aria-label="Dismiss">
            &times;
        </button>

        {{-- "More info" modal --}}
        <div
            x-show="showModal"
            x-on:click.self="showModal = false"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
            style="display: none;">
            <div class="bg-white text-gray-900 rounded-lg shadow-xl max-w-lg w-full mx-4 p-6">
                <h2 class="text-base font-semibold mb-3">Database error detail</h2>
                <pre x-text="detail" class="text-xs bg-gray-100 rounded p-3 overflow-auto max-h-64 whitespace-pre-wrap break-all"></pre>
                <div class="mt-4 flex justify-end">
                    <button
                        type="button"
                        x-on:click="showModal = false"
                        class="px-4 py-1.5 text-sm bg-gray-800 text-white rounded hover:bg-gray-700">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>


    @livewireScriptConfig
    @stack('scripts')
</body>
</html>