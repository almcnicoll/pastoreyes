<div>
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-base font-semibold text-gray-700">Overview</h2>
        @if(!$editing)
            <button wire:click="startEditing"
                    class="text-sm text-indigo-600 hover:underline">Edit</button>
        @endif
    </div>

    @if($editing)
    {{-- Edit Form --}}
    <form wire:submit="save" class="space-y-4">

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

        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Date of Birth</label>
            <input wire:model="date_of_birth" type="date"
                   class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
            <label class="flex items-center gap-2 mt-1 text-xs text-gray-500">
                <input wire:model="dob_year_unknown" type="checkbox" class="rounded">
                Year unknown (day/month only)
            </label>
        </div>

        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Date of Death</label>
            <input wire:model="date_of_death" type="date"
                   class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
        </div>

        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">General Notes</label>
            <textarea wire:model="notes" rows="4"
                      class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500"
                      placeholder="General notes about this person..."></textarea>
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
    {{-- Display Mode --}}
    <div class="space-y-4">

        {{-- Basic Info --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <p class="text-xs text-gray-400 mb-0.5">Gender</p>
                <p class="text-sm text-gray-700">{{ ucfirst($person->gender ?? 'Unknown') }}</p>
            </div>
            @if($person->date_of_birth)
            <div>
                <p class="text-xs text-gray-400 mb-0.5">Date of Birth</p>
                <p class="text-sm text-gray-700">
                    @if($person->dob_year_unknown)
                        {{ $person->date_of_birth->format('j F') }} (year unknown)
                    @else
                        {{ $person->date_of_birth->format('j F Y') }}
                        @if(!$person->date_of_death)
                            <span class="text-gray-400">(age {{ $person->date_of_birth->age }})</span>
                        @endif
                    @endif
                </p>
            </div>
            @endif
            @if($person->date_of_death)
            <div>
                <p class="text-xs text-gray-400 mb-0.5">Date of Death</p>
                <p class="text-sm text-gray-700">{{ $person->date_of_death->format('j F Y') }}</p>
            </div>
            @endif
        </div>

        {{-- Names --}}
        <div>
            <livewire:people.person-show.manage-person-names
                :person="$person"
                :key="'names-'.$person->id" />
        </div>

        {{-- Current Address --}}
        <div>
            <p class="text-xs text-gray-400 mb-2">Current Address</p>
            @php $currentAddress = $person->addresses->where('is_current', true)->first(); @endphp
            @if($currentAddress)
                <p class="text-sm text-gray-700">{{ $currentAddress->formatted }}</p>
                <p class="text-xs text-gray-400 mt-0.5">Added {{ \Carbon\Carbon::parse($currentAddress->date_added)->format('j M Y') }}</p>
            @else
                <p class="text-sm text-gray-400">No address recorded.</p>
            @endif
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
