<div
    x-data="relationshipGraph(@js($graphData), @js($person->id))"
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
            <div class="flex items-center gap-3 bg-white border border-gray-100 rounded-lg px-4 py-2.5">
                <div class="flex-1 min-w-0 flex flex-wrap items-center gap-x-1.5 text-sm">
                    <x-person-name :person="$rel->person" size="sm" />
                    <span class="text-gray-400 text-xs">{{ $rel->labelForPerson($person->id) }}</span>
                    <x-person-name :person="$rel->relatedPerson" size="sm" />
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
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

    {{-- Add/Edit Form --}}
    @if($showForm)
    <div class="bg-gray-50 border border-gray-200 rounded-xl p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-4">
            {{ $editingRelationshipId ? 'Edit Relationship' : 'Add Relationship' }}
        </h3>
        <form wire:submit="saveRelationship" class="space-y-3">

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Related Person</label>
                <livewire:person-search-select wire:model="relatedPersonId"
                    :excludeId="$person->id"
                    :key="'rel-person-search'" />
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Relationship Type</label>
                <select wire:model="relationshipTypeId"
                        class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="">— Select type —</option>
                    @foreach($relationshipTypes as $type)
                        <option value="{{ $type->id }}">
                            {{ $type->name }}
                            @if($type->is_directional && $type->inverse_name)
                                / {{ $type->inverse_name }}
                            @endif
                        </option>
                    @endforeach
                </select>
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
    @endif

</div>

@push('scripts')
<script>
function relationshipGraph(initialData, centralPersonId) {
    return {
        cy: null,

        init() {
            this.cy = cytoscape({
                container: document.getElementById('cy'),
                elements: [...initialData.nodes, ...initialData.edges],
                style: [
                    {
                        selector: 'node',
                        style: {
                            'background-color': 'data(color)',
                            'label': 'data(label)',
                            'color': '#1F2937',
                            'font-size': '11px',
                            'text-valign': 'bottom',
                            'text-margin-y': '4px',
                            'width': '36px',
                            'height': '36px',
                            'border-width': 0,
                        }
                    },
                    {
                        selector: 'node[?isCentral]',
                        style: {
                            'border-width': '3px',
                            'border-color': '#4F46E5',
                            'width': '44px',
                            'height': '44px',
                        }
                    },
                    {
                        selector: 'edge',
                        style: {
                            'width': 2,
                            'line-color': '#D1D5DB',
                            'label': 'data(label)',
                            'font-size': '10px',
                            'color': '#6B7280',
                            'text-rotation': 'autorotate',
                            'text-margin-y': '-8px',
                            'curve-style': 'bezier',
                        }
                    },
                ],
                layout: {
                    name: 'cose',
                    animate: false,
                    nodeDimensionsIncludeLabels: true,
                },
                userZoomingEnabled: true,
                userPanningEnabled: true,
            });

            // Navigate to person profile on node click
            this.cy.on('tap', 'node', (event) => {
                const personId = event.target.data('personId');
                if (personId && personId !== centralPersonId) {
                    window.location.href = `/people/${personId}`;
                }
            });

            // Drag-drop to initiate relationship creation
            this.cy.on('ehcomplete', (event, sourceNode, targetNode) => {
                const fromId = sourceNode.data('personId');
                const toId   = targetNode.data('personId');
                if (fromId && toId && fromId !== toId) {
                    @this.call('initiateRelationshipFromGraph', fromId, toId);
                }
            });
        },

        refreshGraph(newData) {
            if (!this.cy) return;
            this.cy.elements().remove();
            this.cy.add([...newData.nodes, ...newData.edges]);
            this.cy.layout({ name: 'cose', animate: true, nodeDimensionsIncludeLabels: true }).run();
        },
    };
}
</script>
@endpush
