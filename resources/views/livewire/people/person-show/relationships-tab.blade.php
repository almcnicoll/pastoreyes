<div
    x-data="relationshipGraph(@js($graphData), @js($person->id), '{{ rtrim(route('people.show', ['person' => '_placeholder_']), '_placeholder_') }}')"
    x-init="init()"
    @graph:refresh.window="refreshGraph($event.detail.graphData)"
>
    {{-- Controls --}}
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-3">
            <label class="text-xs text-gray-500 font-medium">Depth:</label>
            <div class="flex items-center gap-1">
                @foreach([1,2,3,4] as $d)
                    <button wire:click="$set('depth', {{ $d }})"
                            class="w-7 h-7 rounded text-xs font-medium transition-colors
                                {{ $depth == $d
                                    ? 'bg-indigo-600 text-white'
                                    : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                        {{ $d }}
                    </button>
                @endforeach
            </div>
        </div>
        <button wire:click="$set('showForm', true)"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-indigo-600 text-white text-xs font-medium rounded-lg hover:bg-indigo-700 transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Add Relationship
        </button>
    </div>

    {{-- Graph Container --}}
    <div id="cy" class="cy-container mb-6"></div>

    {{-- Relationship List --}}
    <div class="space-y-2 mb-4">
        @forelse($relationships as $rel)
            @php
                $otherPerson = $rel->otherPerson($person->id);
                $isFromEnd   = $rel->person_id === $person->id;
                // For display we want: [other] [their role relative to central] of [central]
                // "their role relative to central" is the inverse of central's role
                $label = $rel->relationshipType->is_directional
                    ? $rel->relationshipType->labelFromPerspective(!$isFromEnd)
                    : $rel->relationshipType->name;
            @endphp
            <div class="flex items-center gap-3 bg-white border border-gray-100 rounded-lg px-4 py-2.5">
                <div class="flex-1 min-w-0 flex flex-wrap items-center gap-x-1.5 text-sm">
                    <x-person-name :person="$otherPerson" size="sm" />
                    <span class="text-gray-400 text-xs">
                        {{ $label }}{{ $rel->relationshipType->is_directional ? ' of' : '' }}
                    </span>
                    <x-person-name :person="$person" size="sm" />
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                    @if($rel->relationshipType->is_directional)
                        <button wire:click="reverseRelationship({{ $rel->id }})"
                                wire:confirm="Reverse the direction of this relationship?"
                                class="text-xs text-amber-600 hover:underline">Reverse</button>
                    @endif
                    <button wire:click="editRelationship({{ $rel->id }})"
                            class="text-xs text-indigo-600 hover:underline">Edit</button>
                    <button wire:click="deleteRelationship({{ $rel->id }})"
                            wire:confirm="Remove this relationship?"
                            class="text-xs text-red-500 hover:underline">Remove</button>
                </div>
            </div>
        @empty
            <p class="text-sm text-gray-400 py-2">No relationships recorded yet.</p>
        @endforelse
    </div>

    {{-- Add/Edit Relationship Modal --}}
    @if($showForm)
    <div class="fixed inset-0 z-40 flex items-end sm:items-center justify-center"
         @keydown.escape.window="$wire.resetForm()">

        <div class="absolute inset-0 bg-gray-900/50" wire:click="resetForm"></div>

        <div class="relative z-50 w-full sm:max-w-lg bg-white rounded-t-2xl sm:rounded-2xl shadow-xl p-6"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 translate-y-4"
             x-transition:enter-end="opacity-100 translate-y-0">

            <div class="flex items-center justify-between mb-5">
                <h3 class="text-base font-semibold text-gray-800">
                    {{ $editingRelationshipId ? 'Edit Relationship' : 'Add Relationship' }}
                </h3>
                <button wire:click="resetForm" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <form wire:submit="saveRelationship" class="space-y-4">

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Related Person</label>
                    <livewire:person-search-select
                        :excludeId="$person->id"
                        :value="$relatedPersonId"
                        :key="'rel-person-search-'.$person->id" />
                    @error('relatedPersonId')
                        <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Relationship Type</label>
                    <select wire:model="relationshipTypeId"
                            class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">— Select type —</option>
                        @foreach($relationshipTypes as $type)
                            @if($type->is_directional && $type->inverse_name)
                                {{-- Forward: Poppy is the "from" end (e.g. Poppy is parent) --}}
                                <option value="{{ $type->id }}" data-inverse="0"
                                    {{ $relationshipTypeId == $type->id && !$isInverse ? 'selected' : '' }}>
                                    {{ $type->name }}-{{ $type->inverse_name }} ({{ $person->display_name }} is {{ $type->name }} of n)
                                </option>
                                {{-- Inverse: Poppy is the "to" end (e.g. Poppy is child) --}}
                                <option value="{{ $type->id }}" data-inverse="1"
                                    {{ $relationshipTypeId == $type->id && $isInverse ? 'selected' : '' }}>
                                    {{ $type->inverse_name }}-{{ $type->name }} ({{ $person->display_name }} is {{ $type->inverse_name }} of n)
                                </option>
                            @elseif($type->is_directional)
                                {{-- Directional but no inverse name defined --}}
                                <option value="{{ $type->id }}" data-inverse="0"
                                    {{ $relationshipTypeId == $type->id ? 'selected' : '' }}>
                                    {{ $type->name }} ({{ $person->display_name }} is {{ $type->name }})
                                </option>
                            @else
                                <option value="{{ $type->id }}"
                                    {{ $relationshipTypeId == $type->id ? 'selected' : '' }}>
                                    {{ $type->name }}
                                </option>
                            @endif
                        @endforeach
                    </select>
                </div>

                {{-- Track direction for directional types via data-inverse attribute --}}
                <div x-data
                     x-init="
                        $el.closest('form').querySelector('select').addEventListener('change', function(e) {
                            const selected = e.target.options[e.target.selectedIndex];
                            const isInverse = selected.dataset.inverse === '1';
                            $wire.set('isInverse', isInverse);
                        });
                     "></div>

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

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Notes</label>
                    <input wire:model="notes" type="text"
                           class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2"
                           placeholder="Optional context...">
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
    </div>
    @endif

</div>