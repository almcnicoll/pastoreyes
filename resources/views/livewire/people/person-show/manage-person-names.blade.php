<div>
    <div class="flex items-center justify-between mb-3">
        <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Names</p>
        <button wire:click="openAdd"
                class="text-xs text-indigo-600 hover:underline">
            + Add name
        </button>
    </div>

    {{-- Names List --}}
    <div class="space-y-1 mb-3">
        @forelse($names as $name)
            <div class="flex items-center gap-2 py-1.5 border-b border-gray-50 last:border-0">
                <div class="flex-1 min-w-0">
                    <span class="{{ $name->is_primary ? 'font-medium text-gray-800' : 'text-gray-600' }} text-sm">
                        {{ collect([$name->first_name, $name->middle_names, $name->last_name])->filter()->implode(' ') ?: '(no name)' }}
                    </span>
                    @if($name->preferred_name)
                        <span class="text-xs text-gray-400 ml-1">"{{ $name->preferred_name }}"</span>
                    @endif
                    <span class="text-xs text-gray-400 ml-1">({{ $name->type }})</span>
                    @if($name->spelling_uncertain)
                        <span class="text-xs text-amber-500 ml-1">uncertain spelling</span>
                    @endif
                    @if($name->is_primary)
                        <span class="text-xs bg-indigo-50 text-indigo-600 px-1.5 py-0.5 rounded ml-1">primary</span>
                    @endif
                    @if($name->date_from || $name->date_to)
                        <p class="text-xs text-gray-400 mt-0.5">
                            {{ $name->date_from ?? '?' }} — {{ $name->date_to ?? 'present' }}
                        </p>
                    @endif
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                    <button wire:click="edit({{ $name->id }})"
                            class="text-xs text-indigo-600 hover:underline">Edit</button>
                    @if(!$name->is_primary)
                        <button wire:click="delete({{ $name->id }})"
                                wire:confirm="Delete this name?"
                                class="text-xs text-red-500 hover:underline">Delete</button>
                    @endif
                </div>
            </div>
        @empty
            <p class="text-sm text-gray-400">No names recorded.</p>
        @endforelse
    </div>

    {{-- Add/Edit Form --}}
    @if($showForm)
    <div class="bg-gray-50 border border-gray-200 rounded-xl p-4 mt-3">
        <h4 class="text-xs font-semibold text-gray-700 mb-3">
            {{ $editingId ? 'Edit Name' : 'Add Name' }}
        </h4>
        <form wire:submit="save" class="space-y-3">

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">First Name</label>
                    <input wire:model="firstName" type="text"
                           class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                    @error('firstName')
                        <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Last Name</label>
                    <input wire:model="lastName" type="text"
                           class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
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

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Type</label>
                    <select wire:model="type"
                            class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="birth">Birth</option>
                        <option value="married">Married</option>
                        <option value="preferred">Preferred</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Notes</label>
                    <input wire:model="notes" type="text"
                           class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500"
                           placeholder="Optional context">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">From Date</label>
                    <input wire:model="dateFrom" type="date"
                           class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">To Date</label>
                    <input wire:model="dateTo" type="date"
                           class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2">
                </div>
            </div>

            <div class="flex items-center gap-4">
                <label class="flex items-center gap-2 text-xs text-gray-600">
                    <input wire:model="spellingUncertain" type="checkbox" class="rounded">
                    Spelling uncertain
                </label>
                <label class="flex items-center gap-2 text-xs text-gray-600">
                    <input wire:model="isPrimary" type="checkbox" class="rounded">
                    Set as primary name
                </label>
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
