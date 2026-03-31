<?php

namespace App\Livewire\People\PersonShow;

use App\Models\Person;
use App\Models\Relationship;
use App\Models\RelationshipType;
use Livewire\Component;

class RelationshipsTab extends Component
{
    public Person $person;
    public int $depth = 2;

    // Add/edit relationship form
    public bool $showForm = false;
    public ?int $editingRelationshipId = null;
    public ?int $relatedPersonId = null;
    public ?int $relationshipTypeId = null;
    public string $notes = '';
    public ?string $dateFrom = null;
    public ?string $dateTo = null;

    // Drag-drop initiated relationship
    public ?int $dragFromPersonId = null;
    public ?int $dragToPersonId = null;

    protected $listeners = [
        'graph:initiateRelationship' => 'initiateRelationshipFromGraph',
        'person-selected'            => 'onPersonSelected',
    ];

    public function onPersonSelected(int $personId): void
    {
        $this->relatedPersonId = $personId;
    }

    public function mount(Person $person): void
    {
        $this->person = $person;
    }

    public function updatedDepth(): void
    {
        // Clamp depth between 1 and 4
        $this->depth = max(1, min(4, (int) $this->depth));
        $this->dispatch('graph:refresh', graphData: $this->buildGraphData());
    }

    /**
     * Build Cytoscape-compatible node/edge data for the graph.
     * Traverses relationships up to $depth levels from the central person.
     */
    public function buildGraphData(): array
    {
        $userId     = auth()->id();
        $visited    = collect();
        $nodes      = collect();
        $edges      = collect();
        $queue      = collect([['id' => $this->person->id, 'level' => 0]]);
        $genderColors = config('entry_types.gender_colors');

        while ($queue->isNotEmpty()) {
            $current = $queue->shift();
            $personId = $current['id'];
            $level    = $current['level'];

            if ($visited->contains($personId)) {
                continue;
            }
            $visited->push($personId);

            $p = Person::with('primaryName')->find($personId);
            if (!$p || $p->user_id !== $userId) {
                continue;
            }

            $nodes->push([
                'data' => [
                    'id'        => 'p' . $p->id,
                    'label'     => $p->display_name,
                    'personId'  => $p->id,
                    'isCentral' => $p->id === $this->person->id,
                    'color'     => $genderColors[$p->gender ?? 'unknown'] ?? $genderColors['unknown'],
                ],
            ]);

            if ($level < $this->depth) {
                $relationships = Relationship::where('user_id', $userId)
                    ->where(fn($q) => $q->where('person_id', $personId)
                        ->orWhere('related_person_id', $personId))
                    ->with('relationshipType')
                    ->get();

                foreach ($relationships as $rel) {
                    $otherId = $rel->person_id === $personId
                        ? $rel->related_person_id
                        : $rel->person_id;

                    // Add edge (avoid duplicates)
                    $edgeId = 'e' . min($personId, $otherId) . '_' . max($personId, $otherId) . '_' . $rel->id;
                    if (!$edges->contains('data.id', $edgeId)) {
                        $edges->push([
                            'data' => [
                                'id'     => $edgeId,
                                'source' => 'p' . $rel->person_id,
                                'target' => 'p' . $rel->related_person_id,
                                // Label from the perspective of the central person
                                'label'  => $rel->labelForPerson($this->person->id),
                                'relId'  => $rel->id,
                            ],
                        ]);
                    }

                    if (!$visited->contains($otherId)) {
                        $queue->push(['id' => $otherId, 'level' => $level + 1]);
                    }
                }
            }
        }

        return [
            'nodes' => $nodes->values()->toArray(),
            'edges' => $edges->values()->toArray(),
        ];
    }

    public function initiateRelationshipFromGraph(int $fromPersonId, int $toPersonId): void
    {
        $this->dragFromPersonId  = $fromPersonId;
        $this->dragToPersonId    = $toPersonId;
        $this->relatedPersonId   = $toPersonId === $this->person->id ? $fromPersonId : $toPersonId;
        $this->showForm          = true;
        $this->editingRelationshipId = null;
    }

    public function editRelationship(int $relationshipId): void
    {
        $rel = Relationship::findOrFail($relationshipId);
        abort_if($rel->user_id !== auth()->id(), 403);

        $this->editingRelationshipId = $rel->id;
        $this->relatedPersonId       = $rel->person_id === $this->person->id
            ? $rel->related_person_id
            : $rel->person_id;
        $this->relationshipTypeId    = $rel->relationship_type_id;
        $this->notes                 = $rel->notes ?? '';
        $this->dateFrom              = $rel->date_from;
        $this->dateTo                = $rel->date_to;
        $this->showForm              = true;
    }

    public function saveRelationship(): void
    {
        $this->validate([
            'relatedPersonId'   => 'required|integer|exists:persons,id',
            'relationshipTypeId' => 'required|integer|exists:relationship_types,id',
            'notes'             => 'nullable|string|max:1000',
            'dateFrom'          => 'nullable|date',
            'dateTo'            => 'nullable|date|after_or_equal:dateFrom',
        ]);

        $data = [
            'user_id'              => auth()->id(),
            'person_id'            => $this->person->id,
            'related_person_id'    => $this->relatedPersonId,
            'relationship_type_id' => $this->relationshipTypeId,
            'notes'                => $this->notes ?: null,
            'date_from'            => $this->dateFrom,
            'date_to'              => $this->dateTo,
        ];

        if ($this->editingRelationshipId) {
            $rel = Relationship::findOrFail($this->editingRelationshipId);
            abort_if($rel->user_id !== auth()->id(), 403);
            $rel->update($data);
        } else {
            Relationship::create($data);
        }

        $this->resetForm();
        $this->dispatch('graph:refresh', graphData: $this->buildGraphData());
        $this->dispatch('notify', message: 'Relationship saved.');
    }

    public function deleteRelationship(int $relationshipId): void
    {
        $rel = Relationship::findOrFail($relationshipId);
        abort_if($rel->user_id !== auth()->id(), 403);
        $rel->delete();

        $this->dispatch('graph:refresh', graphData: $this->buildGraphData());
        $this->dispatch('notify', message: 'Relationship removed.');
    }

    public function resetForm(): void
    {
        $this->showForm              = false;
        $this->editingRelationshipId = null;
        $this->relatedPersonId       = null;
        $this->relationshipTypeId    = null;
        $this->notes                 = '';
        $this->dateFrom              = null;
        $this->dateTo                = null;
        $this->dragFromPersonId      = null;
        $this->dragToPersonId        = null;
    }

    public function render()
    {
        $relationshipTypes = RelationshipType::availableTo(auth()->id())->get();

        $relationships = Relationship::where('user_id', auth()->id())
            ->where(fn($q) => $q->where('person_id', $this->person->id)
                ->orWhere('related_person_id', $this->person->id))
            ->with(['relationshipType', 'person.primaryName', 'relatedPerson.primaryName'])
            ->get();

        return view('livewire.people.person-show.relationships-tab', [
            'graphData'         => $this->buildGraphData(),
            'relationships'     => $relationships,
            'relationshipTypes' => $relationshipTypes,
        ]);
    }
}
