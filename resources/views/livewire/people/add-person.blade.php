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
                <h2 class="text-base font-semibold text-gray-800">Add Person</h2>
                <button wire:click="closeModal" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <form wire:submit="save" class="space-y-4">

                {{-- Name fields --}}
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">First Name</label>
                        <input wire:model="firstName" type="text" autofocus
                               class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500"
                               placeholder="First name">
                        @error('firstName')
                            <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Last Name</label>
                        <input wire:model="lastName" type="text"
                               class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500"
                               placeholder="Last name">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">
                        Preferred Name <span class="text-gray-400">(optional)</span>
                    </label>
                    <input wire:model="preferredName" type="text"
                           class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500"
                           placeholder="Nickname or preferred first name">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Name Type</label>
                        <select wire:model="nameType"
                                class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="birth">Birth name</option>
                            <option value="married">Married name</option>
                            <option value="preferred">Preferred name</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Gender</label>
                        <select wire:model="gender"
                                class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="">— Unknown —</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="unknown">Other / Unknown</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="flex items-center gap-2 text-xs text-gray-600 mb-2">
                        <input wire:model="spellingUncertain" type="checkbox" class="rounded">
                        Spelling uncertain
                    </label>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">
                        Date of Birth <span class="text-gray-400">(optional)</span>
                    </label>
                    <input wire:model="dateOfBirth" type="date"
                           class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                    <label class="flex items-center gap-2 mt-1 text-xs text-gray-500">
                        <input wire:model="dobYearUnknown" type="checkbox" class="rounded">
                        Year unknown (day/month only)
                    </label>
                </div>

                <div class="flex gap-3 pt-2">
                    <button type="submit"
                            class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                        Add Person
                    </button>
                    <button type="button" wire:click="closeModal"
                            class="px-4 py-2 border border-gray-300 text-gray-600 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                </div>

            </form>
        </div>
    </div>
    @endif
</div>
