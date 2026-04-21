<div>
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-base font-semibold text-gray-700">Overview</h2>
        @if(!$editing)
            <button wire:click="startEditing"
                    class="text-sm text-indigo-600 hover:underline">Edit</button>
        @endif
    </div>

    @if($editing)
    {{-- ================================================================
         EDIT FORM
         ================================================================ --}}
    <form wire:submit="save" class="space-y-5">

        {{-- Photo --}}
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-2">Photo</label>
            <div class="flex items-center gap-4">
                <div class="w-16 h-16 rounded-full overflow-hidden bg-gray-100 flex items-center justify-center flex-shrink-0">
                    @if($photoUpload)
                        <img src="{{ $photoUpload->temporaryUrl() }}" alt="Preview" class="w-full h-full object-cover">
                    @elseif($person->photo && !$removePhoto)
                        <img src="{{ $person->photo->data_uri }}" alt="{{ $person->display_name }}" class="w-full h-full object-cover">
                    @else
                        <span class="text-xl font-bold text-gray-400">
                            {{ strtoupper(substr($person->display_name, 0, 1)) }}
                        </span>
                    @endif
                </div>
                <div class="space-y-1.5">
                    <label class="cursor-pointer inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 text-gray-700 text-xs font-medium rounded-lg hover:bg-gray-200 transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                        </svg>
                        {{ $person->photo ? 'Replace photo' : 'Upload photo' }}
                        <input wire:model="photoUpload" type="file" accept="image/*" class="hidden">
                    </label>
                    @if($person->photo && !$removePhoto)
                        <label class="flex items-center gap-1.5 text-xs text-red-500 cursor-pointer">
                            <input wire:model="removePhoto" type="checkbox" class="rounded">
                            Remove photo
                        </label>
                    @endif
                    @error('photoUpload')
                        <p class="text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        {{-- Primary Name --}}
        <div class="border-t border-gray-100 pt-4">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-3">Primary Name</p>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">First Name</label>
                    <input wire:model="firstName" type="text"
                           class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Last Name</label>
                    <input wire:model="lastName" type="text"
                           class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3 mt-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Middle Names</label>
                    <input wire:model="middleNames" type="text"
                           class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Preferred Name</label>
                    <input wire:model="preferredName" type="text"
                           class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500"
                           placeholder="Nickname">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3 mt-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Name Type</label>
                    <select wire:model="nameType"
                            class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="birth">Birth</option>
                        <option value="married">Married</option>
                        <option value="preferred">Preferred</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="flex items-end pb-2">
                    <label class="flex items-center gap-2 text-xs text-gray-600">
                        <input wire:model="spellingUncertain" type="checkbox" class="rounded">
                        Spelling uncertain
                    </label>
                </div>
            </div>
        </div>

        {{-- Personal Details --}}
        <div class="border-t border-gray-100 pt-4">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-3">Personal Details</p>

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

            {{-- Birthday (stored as KeyDate) --}}
            <div class="mt-3">
                <label class="block text-xs font-medium text-gray-600 mb-1">
                    Birthday
                    <span class="text-gray-400 font-normal">(also shown under Key Dates)</span>
                </label>
                <input wire:model="birthday" type="date"
                       class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                <label class="flex items-center gap-2 mt-1 text-xs text-gray-500">
                    <input wire:model="birthdayYearUnknown" type="checkbox" class="rounded">
                    Year unknown (day/month only)
                </label>
                @error('birthday')
                    <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="mt-3">
                <label class="block text-xs font-medium text-gray-600 mb-1">Date of Death</label>
                <input wire:model="date_of_death" type="date"
                       class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
            </div>

            <div class="mt-3">
                <label class="block text-xs font-medium text-gray-600 mb-1">General Notes</label>
                <textarea wire:model="notes" rows="4"
                          class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500"
                          placeholder="General notes about this person..."></textarea>
            </div>
        </div>

        <div class="flex gap-3 pt-2">
            <button type="submit"
                    class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                Save
            </button>
            <button type="button" wire:click="cancelEditing"
                    class="px-4 py-2 border border-gray-300 text-gray-600 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">
                Cancel
            </button>
        </div>

    </form>

    @else
    {{-- ================================================================
         DISPLAY MODE
         ================================================================ --}}
    <div class="space-y-5">

        {{-- Photo + Name --}}
        <div class="flex items-center gap-4">
            <div class="w-16 h-16 rounded-full overflow-hidden bg-gray-100 flex items-center justify-center flex-shrink-0">
                @if($person->photo)
                    <img src="{{ $person->photo->data_uri }}"
                         alt="{{ $person->display_name }}" class="w-full h-full object-cover">
                @else
                    <span class="text-xl font-bold"
                          style="color: {{ config('entry_types.gender_colors')[$person->gender ?? 'unknown'] }}">
                        {{ strtoupper(substr($person->display_name, 0, 1)) }}
                    </span>
                @endif
            </div>
            <div>
                <p class="text-lg font-semibold text-gray-800">{{ $person->display_name }}</p>
                @if($person->primaryName && $person->primaryName->spelling_uncertain)
                    <p class="text-xs text-amber-500">Spelling uncertain</p>
                @endif
            </div>
        </div>

        {{-- Personal Details --}}
        @php
            $birthdayKd = $person->keyDates()->where('type', 'birthday')->first();
            $deathDate  = $person->date_of_death ? \Carbon\Carbon::parse($person->date_of_death) : null;
        @endphp

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <p class="text-xs text-gray-400 mb-0.5">Gender</p>
                <p class="text-sm text-gray-700">{{ ucfirst($person->gender ?? 'Unknown') }}</p>
            </div>

            @if($birthdayKd)
            <div>
                <p class="text-xs text-gray-400 mb-0.5">Birthday</p>
                <p class="text-sm text-gray-700">
                    @if($birthdayKd->year_unknown)
                        {{ $birthdayKd->date->format('j F') }} (year unknown)
                    @else
                        {{ $birthdayKd->date->format('j F Y') }}
                        @if(!$deathDate)
                            <span class="text-gray-400">(age {{ $birthdayKd->date->age }})</span>
                        @endif
                    @endif
                </p>
            </div>
            @endif

            @if($deathDate)
            <div>
                <p class="text-xs text-gray-400 mb-0.5">Date of Death</p>
                <p class="text-sm text-gray-700">{{ $deathDate->format('j F Y') }}</p>
            </div>
            @endif
        </div>

        {{-- Names --}}
        <div>
            <livewire:people.person-show.manage-person-names
                :person="$person"
                :key="'names-'.$person->id" />
        </div>

        {{-- Addresses --}}
        <div>
            <livewire:people.person-show.manage-addresses
                :person="$person"
                :key="'addresses-'.$person->id" />
        </div>

        {{-- Google Contact --}}
        <div>
            <p class="text-xs text-gray-400 mb-2">Google Contact</p>
            @if($person->google_contact_id)
                <a href="https://contacts.google.com/person/{{ $person->google_contact_id }}"
                   target="_blank"
                   class="inline-flex items-center gap-1.5 text-sm text-indigo-600 hover:underline">
                    View in Google Contacts
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                    </svg>
                </a>
            @else
                <button wire:click="$dispatch('open-link-google-contact', { personId: {{ $person->id }} })"
                        class="text-sm text-indigo-600 hover:underline">
                    Find in Google Contacts
                </button>
            @endif
        </div>

        {{-- General Notes --}}
        @if($person->notes)
        <div>
            <p class="text-xs text-gray-400 mb-2">Notes</p>
            <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ $person->notes }}</p>
        </div>
        @endif

    </div>
    @endif

</div>
