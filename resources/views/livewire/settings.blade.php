<div>
    <h1 class="text-2xl font-bold text-gray-800 mb-6">Settings</h1>

    {{-- Tab Bar --}}
    <div class="border-b border-gray-200 mb-6">
        <nav class="-mb-px flex gap-1 overflow-x-auto">
            @php
                $tabs = [
                    'account'       => 'Account',
                    'google'        => 'Google',
                    'appearance'    => 'Appearance',
                    'rel_types'     => 'Relationship Types',
                    'key_dates'     => 'Key Date Defaults',
                ];
                if(auth()->user()->is_admin) {
                    $tabs['users'] = 'User Management';
                }
            @endphp
            @foreach($tabs as $tab => $label)
                <button wire:click="setTab('{{ $tab }}')"
                        class="whitespace-nowrap px-4 py-2 text-sm font-medium border-b-2 transition-colors
                            {{ $activeTab === $tab
                                ? 'border-indigo-600 text-indigo-600'
                                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                    {{ $label }}
                    @if($tab === 'users' && auth()->user()->is_admin)
                        <span class="ml-1 text-xs bg-indigo-100 text-indigo-600 px-1.5 py-0.5 rounded-full">Admin</span>
                    @endif
                </button>
            @endforeach
        </nav>
    </div>

    {{-- Account Tab --}}
    @if($activeTab === 'account')
    <div class="max-w-md space-y-4">

        @if(auth()->user()->is_admin)
            <div class="inline-flex items-center gap-1.5 px-3 py-1 bg-indigo-50 text-indigo-700 text-xs font-medium rounded-full mb-2">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
                Administrator
            </div>
        @endif

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
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Email</label>
            <p class="text-sm text-gray-500">{{ auth()->user()->email }}</p>
            <p class="text-xs text-gray-400 mt-0.5">Managed by your Google account.</p>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Account Created</label>
            <p class="text-sm text-gray-500">{{ auth()->user()->created_at->format('j F Y') }}</p>
        </div>

        <button wire:click="saveAccount"
                class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
            Save Changes
        </button>

        <div class="pt-6 border-t border-gray-100">
            <p class="text-xs font-medium text-red-600 mb-2">Danger Zone</p>
            <button wire:click="deleteOwnAccount"
                    wire:confirm="This will permanently delete your account and ALL your data. This cannot be undone. Type DELETE to confirm.|DELETE"
                    class="px-4 py-2 border border-red-300 text-red-600 text-sm font-medium rounded-lg hover:bg-red-50 transition-colors">
                Delete My Account
            </button>
        </div>
    </div>

    {{-- Google Tab --}}
    @elseif($activeTab === 'google')
    <div class="max-w-md space-y-6">
        <livewire:google-integration-settings />
    </div>

    {{-- Appearance Tab --}}
    @elseif($activeTab === 'appearance')
    <div class="max-w-lg space-y-6">

        <div>
            <h3 class="text-sm font-semibold text-gray-700 mb-3">Gender Colours</h3>
            <div class="space-y-2">
                @foreach(['male' => 'Male', 'female' => 'Female', 'unknown' => 'Other / Unknown'] as $key => $label)
                <div class="flex items-center gap-3">
                    <input type="color"
                           wire:model.live="genderColors.{{ $key }}"
                           class="w-8 h-8 rounded cursor-pointer border border-gray-200">
                    <label class="text-sm text-gray-600">{{ $label }}</label>
                    <span class="text-xs text-gray-400 font-mono">{{ $genderColors[$key] ?? '' }}</span>
                </div>
                @endforeach
            </div>
        </div>

        <div>
            <h3 class="text-sm font-semibold text-gray-700 mb-3">Significance Badge Colours</h3>
            <div class="space-y-2">
                @foreach([1,2,3,4,5] as $s)
                <div class="flex items-center gap-3">
                    <input type="color"
                           wire:model.live="significanceColors.{{ $s }}"
                           class="w-8 h-8 rounded cursor-pointer border border-gray-200">
                    <label class="text-sm text-gray-600">{{ $s }} — {{ ['Low','','Medium','','High'][$s-1] }}</label>
                    <div class="w-6 h-6 rounded-full text-white text-xs font-bold flex items-center justify-center"
                         style="background-color: {{ $significanceColors[$s] ?? '#ccc' }}">{{ $s }}</div>
                </div>
                @endforeach
            </div>
        </div>

        <div>
            <h3 class="text-sm font-semibold text-gray-700 mb-3">Entry Type Colours</h3>
            <div class="space-y-2">
                @foreach(config('entry_types.types') as $key => $config)
                <div class="flex items-center gap-3">
                    <input type="color"
                           wire:model.live="entryTypeColors.{{ $key }}"
                           class="w-8 h-8 rounded cursor-pointer border border-gray-200">
                    <label class="text-sm text-gray-600">{{ $config['label'] }}</label>
                </div>
                @endforeach
            </div>
        </div>

        <div>
            <h3 class="text-sm font-semibold text-gray-700 mb-2">Dashboard — Upcoming Window</h3>
            <div class="flex items-center gap-3">
                <input wire:model="upcomingDaysWindow" type="number" min="7" max="365"
                       class="w-24 border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                <span class="text-sm text-gray-500">days</span>
            </div>
        </div>

        <button wire:click="saveAppearance"
                class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
            Save Appearance
        </button>

    </div>

    {{-- Relationship Types Tab --}}
    @elseif($activeTab === 'rel_types')
    <div class="max-w-lg">

        {{-- My Custom Types --}}
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-semibold text-gray-700">My Custom Types</h3>
            <button wire:click="openAddRelType"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-indigo-600 text-white text-xs font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Add Type
            </button>
        </div>

        <div class="space-y-2 mb-6">
            @forelse($customRelTypes as $rt)
                <div class="flex items-center gap-3 bg-white border border-gray-100 rounded-lg px-4 py-2.5">
                    <div class="flex-1 text-sm text-gray-700">
                        {{ $rt->name }}
                        @if($rt->is_directional && $rt->inverse_name)
                            <span class="text-gray-400 mx-1">/</span>{{ $rt->inverse_name }}
                            <span class="text-xs text-gray-400 ml-1">(directional)</span>
                        @endif
                    </div>
                    <button wire:click="editRelType({{ $rt->id }})"
                            class="text-xs text-indigo-600 hover:underline">Edit</button>
                    <button wire:click="deleteRelType({{ $rt->id }})"
                            wire:confirm="Delete this relationship type?"
                            class="text-xs text-red-500 hover:underline">Delete</button>
                </div>
            @empty
                <p class="text-sm text-gray-400">No custom types yet.</p>
            @endforelse
        </div>

        {{-- Global Custom Types (admin only) --}}
        @if(auth()->user()->is_admin)
        <div class="mb-6">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-semibold text-gray-700">
                    Global Custom Types
                    <span class="text-xs font-normal text-gray-400 ml-1">(available to all users)</span>
                </h3>
            </div>
            <div class="space-y-2">
                @forelse($globalCustomRelTypes as $rt)
                    <div class="flex items-center gap-3 bg-white border border-gray-100 rounded-lg px-4 py-2.5">
                        <div class="flex-1 text-sm text-gray-700">
                            {{ $rt->name }}
                            @if($rt->is_directional && $rt->inverse_name)
                                <span class="text-gray-400 mx-1">/</span>{{ $rt->inverse_name }}
                                <span class="text-xs text-gray-400 ml-1">(directional)</span>
                            @endif
                        </div>
                        <button wire:click="editRelType({{ $rt->id }})"
                                class="text-xs text-indigo-600 hover:underline">Edit</button>
                        <button wire:click="deleteRelType({{ $rt->id }})"
                                wire:confirm="Delete this global relationship type?"
                                class="text-xs text-red-500 hover:underline">Delete</button>
                    </div>
                @empty
                    <p class="text-sm text-gray-400">No global custom types yet.</p>
                @endforelse
            </div>
        </div>
        @endif

        {{-- Add/Edit Form --}}
        @if($showRelTypeForm)
        <div class="bg-gray-50 border border-gray-200 rounded-xl p-5 mb-6">
            <h4 class="text-sm font-semibold text-gray-700 mb-3">
                {{ $editingRelTypeId ? 'Edit Type' : 'Add Type' }}
            </h4>
            <form wire:submit="saveRelType" class="space-y-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Name</label>
                    <input wire:model="relTypeName" type="text"
                           class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2"
                           placeholder="e.g. mentor, colleague">
                </div>
                <label class="flex items-center gap-2 text-xs text-gray-600">
                    <input wire:model.live="relTypeIsDirectional" type="checkbox" class="rounded">
                    Directional relationship
                </label>
                @if($relTypeIsDirectional)
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Inverse Name</label>
                    <input wire:model="relTypeInverseName" type="text"
                           class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2"
                           placeholder="e.g. mentee (inverse of mentor)">
                </div>
                @endif
                @if(auth()->user()->is_admin)
                <label class="flex items-center gap-2 text-xs text-gray-600">
                    <input wire:model="relTypeIsGlobal" type="checkbox" class="rounded">
                    Make global <span class="text-gray-400">(available to all users, not just me)</span>
                </label>
                @endif
                <x-form-actions cancelAction="resetRelTypeForm" />
            </form>
        </div>
        @endif

        {{-- Preset Types (read-only) --}}
        <div>
            <h3 class="text-sm font-semibold text-gray-700 mb-3">
                Global Presets
                <span class="text-xs font-normal text-gray-400 ml-1">(read-only)</span>
            </h3>
            <div class="space-y-1">
                @foreach($presetRelTypes as $rt)
                    <div class="text-sm text-gray-500 px-2 py-1">
                        {{ $rt->name }}
                        @if($rt->is_directional && $rt->inverse_name)
                            / {{ $rt->inverse_name }}
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

    </div>

    {{-- Key Date Defaults Tab --}}
    @elseif($activeTab === 'key_dates')
    <div class="max-w-md space-y-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Upcoming Key Dates Window</label>
            <p class="text-xs text-gray-400 mb-2">How many days ahead to show on the Dashboard.</p>
            <div class="flex items-center gap-3">
                <input wire:model="upcomingDaysWindow" type="number" min="7" max="365"
                       class="w-24 border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                <span class="text-sm text-gray-500">days</span>
            </div>
        </div>
        <button wire:click="saveAppearance"
                class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
            Save
        </button>
    </div>

    {{-- User Management Tab (admin only) --}}
    @elseif($activeTab === 'users' && auth()->user()->is_admin)
    <div class="max-w-lg">

        {{-- User Search --}}
        <div class="mb-4">
            <label class="block text-xs font-medium text-gray-600 mb-1">Search Users</label>
            <input wire:model.live.debounce.300ms="userSearch"
                   type="text"
                   placeholder="Search by name or email..."
                   class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
        </div>

        {{-- Search Results --}}
        @if($users->isNotEmpty())
        <div class="bg-white border border-gray-200 rounded-lg divide-y divide-gray-50 mb-6">
            @foreach($users as $u)
                <button wire:click="loadManagedUser({{ $u->id }})"
                        class="w-full flex items-center gap-3 px-4 py-3 hover:bg-gray-50 transition-colors text-left
                            {{ $managedUserId === $u->id ? 'bg-indigo-50' : '' }}">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-800">
                            {{ $u->first_name }} {{ $u->last_name }}
                        </p>
                        <p class="text-xs text-gray-400">{{ $u->email }}</p>
                    </div>
                    @if(!$u->is_active)
                        <span class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full">Disabled</span>
                    @endif
                    @if($u->is_admin)
                        <span class="text-xs bg-indigo-100 text-indigo-700 px-2 py-0.5 rounded-full">Admin</span>
                    @endif
                </button>
            @endforeach
        </div>
        @elseif(strlen($userSearch) >= 2)
            <p class="text-sm text-gray-400 mb-6">No users found.</p>
        @endif

        {{-- Managed User Form --}}
        @if($managedUserId)
        <div class="bg-gray-50 border border-gray-200 rounded-xl p-5 space-y-4">

            @if($managedUserDirty)
                <div class="text-xs text-amber-600 bg-amber-50 border border-amber-200 rounded px-3 py-2">
                    Unsaved changes — remember to save before switching to another user.
                </div>
            @endif

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">First Name</label>
                    <input wire:model="managedFirstName" type="text"
                           class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Last Name</label>
                    <input wire:model="managedLastName" type="text"
                           class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2">
                </div>
            </div>

            <div class="space-y-2">
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input wire:model="managedIsActive" type="checkbox" class="rounded">
                    Account active
                    @if(!$managedIsActive)
                        <span class="text-xs text-amber-600">— user will be unable to log in</span>
                    @endif
                </label>
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input wire:model="managedIsAdmin" type="checkbox" class="rounded"
                           @if($managedUserId === auth()->id()) disabled @endif>
                    Administrator
                    @if($managedUserId === auth()->id())
                        <span class="text-xs text-gray-400">— cannot change your own admin status</span>
                    @endif
                </label>
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button wire:click="saveManagedUser"
                        class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                    Save Changes
                </button>
                <button wire:click="deleteManagedUser"
                        wire:confirm="Permanently delete this user and all their data? This cannot be undone."
                        class="px-4 py-2 border border-red-300 text-red-600 text-sm font-medium rounded-lg hover:bg-red-50 transition-colors">
                    Delete User
                </button>
            </div>
        </div>
        @endif

    </div>
    @endif

</div>
