<?php

namespace App\Http\Controllers\Api;

use App\Models\Relationship;
use App\Models\RelationshipType;
use App\Services\Api\ApiResponse;
use App\Services\Api\Narrator;
use App\Services\PersonSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RelationshipsController extends ApiController
{
    public function types(): JsonResponse
    {
        $types = RelationshipType::availableTo($this->userId())->orderBy('name')->get()->map(fn($t) => [
            'id'             => $t->id,
            'name'           => $t->name,
            'inverse_name'   => $t->is_directional ? $t->inverse_name : null,
            'is_directional' => $t->is_directional,
            'source'         => $t->user_id === null ? 'global' : 'yours',
            'example'        => $t->is_directional && $t->inverse_name
                ? "A is {$t->name} of B, so B is {$t->inverse_name} of A"
                : "A is {$t->name} of B, and B is {$t->name} of A",
        ]);

        $this->summarise('listed relationship types');

        return ApiResponse::ok(Narrator::plural($types->count(), 'relationship type') . ' available. Give either side\'s name as "type" when adding a relationship; the direction follows.', $types->all());
    }

    /** Add a relationship from {id} to `other`. `type` may be either side's name: "father" or "child". */
    public function store(Request $request, int $id): JsonResponse
    {
        $input = $this->input($request, [
            'type'  => 'required|string|max:100',
            'other' => 'required',
            'notes' => 'nullable|string|max:1000',
        ], doing: 'add that relationship');

        $person = $this->personById($id);
        $other  = $this->person($input['other'], write: true);

        if ($other->id === $person->id) {
            return ApiResponse::fieldError('other', 'A relationship needs two different people.', 'add that relationship');
        }

        [$type, $personIsFrom] = $this->matchType($input['type']);

        // "Jim is father of Al": the *from* end holds the type's name, the *to* end its inverse
        [$from, $to] = $personIsFrom ? [$person, $other] : [$other, $person];
        $fromRole = $type->name;
        $toRole   = $type->is_directional && $type->inverse_name ? $type->inverse_name : $type->name;

        $existing = Relationship::where('user_id', $this->userId())->where('relationship_type_id', $type->id)
            ->where(fn($q) => $q->where(['person_id' => $from->id, 'related_person_id' => $to->id])
                ->orWhere(fn($q) => $q->where(['person_id' => $to->id, 'related_person_id' => $from->id])))
            ->first();

        $sentence = "{$from->display_name} is {$fromRole} of {$to->display_name} (so {$to->display_name} is {$toRole} of {$from->display_name})";

        if ($existing) {
            $this->warn('That relationship is already recorded, so nothing was added.');
            return ApiResponse::ok("Already recorded: {$sentence}.", ['relationship_id' => $existing->id], $this->warnings());
        }

        if (ApiResponse::isDryRun()) {
            return ApiResponse::ok("Would record: {$sentence}.", null, $this->warnings());
        }

        $relationship = Relationship::create([
            'user_id' => $this->userId(), 'person_id' => $from->id, 'related_person_id' => $to->id,
            'relationship_type_id' => $type->id, 'notes' => $input['notes'] ?? null,
        ]);

        $this->summarise('added a relationship');

        return ApiResponse::created("Recorded: {$sentence}.", ['relationship_id' => $relationship->id], $this->warnings());
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->input($request, [], doing: 'remove that relationship');

        $relationship = Relationship::where('user_id', $this->userId())->with(['person.primaryName', 'relatedPerson.primaryName', 'relationshipType'])->find($id);
        if (!$relationship) {
            return ApiResponse::notFound("There is no relationship with id {$id}.");
        }

        $warning = "Remove this relationship ({$relationship->person->display_name} is {$relationship->relationshipType->name} of {$relationship->relatedPerson->display_name})? Neither person is deleted.";

        if ($response = $this->confirmDelete($request, "relationship_{$id}", $warning)) {
            return $response;
        }

        $relationship->delete();
        $this->summarise('removed a relationship');

        return ApiResponse::ok('Removed the relationship.');
    }

    /**
     * Which relationship type, and whether the person is on its "from" end. A name matching a type's
     * own name wins; the inverse names are only used when no type is called that. Several equally
     * good matches is a question for the agent, not a guess.
     *
     * @return array{0: RelationshipType, 1: bool}
     */
    private function matchType(string $given): array
    {
        $wanted = PersonSearch::norm(preg_replace('/\s+of$/i', '', trim($given)));
        $types  = RelationshipType::availableTo($this->userId())->get();

        $named   = $types->filter(fn($t) => PersonSearch::norm($t->name) === $wanted);
        $inverse = $types->filter(fn($t) => $t->is_directional && $t->inverse_name && PersonSearch::norm($t->inverse_name) === $wanted);

        $matches = $named->isNotEmpty() ? $named : $inverse;
        $isFrom  = $named->isNotEmpty();

        if ($matches->count() !== 1) {
            $options = $types->map(fn($t) => $t->name . ($t->is_directional && $t->inverse_name ? " / {$t->inverse_name}" : ''))->all();
            $this->halt(ApiResponse::invalid(
                $matches->isEmpty()
                    ? "I don't know a relationship type called '{$given}'. GET /api/v1/relationship-types lists them: " . Narrator::join($options) . '.'
                    : "'{$given}' fits several relationship types (" . Narrator::join($matches->map->name->all()) . '): say which, using the type\'s own name.',
                [['field' => 'type', 'code' => 'unknown_type', 'message' => 'Unrecognised or ambiguous relationship type.']]
            ));
        }

        return [$matches->first(), $isFrom];
    }
}
