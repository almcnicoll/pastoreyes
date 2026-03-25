<?php

namespace App\Models;

use App\Casts\EncryptedCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Relationship extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'person_id',
        'related_person_id',
        'relationship_type_id',
        'notes',
        'date_from',
        'date_to',
    ];

    protected function casts(): array
    {
        return [
            'notes'     => EncryptedCast::class,
            'date_from' => EncryptedCast::class,
            'date_to'   => EncryptedCast::class,
        ];
    }

    /**
     * Get the relationship label from the perspective of a given person.
     * Handles directionality transparently.
     */
    public function labelForPerson(int $personId): string
    {
        $isFromEnd = ($this->person_id === $personId);
        return $this->relationshipType->labelFromPerspective($isFromEnd);
    }

    /**
     * Get the "other" person in the relationship relative to a given person ID.
     */
    public function otherPerson(int $personId): Person
    {
        return $this->person_id === $personId
            ? $this->relatedPerson
            : $this->person;
    }

    // Relationships

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'person_id');
    }

    public function relatedPerson(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'related_person_id');
    }

    public function relationshipType(): BelongsTo
    {
        return $this->belongsTo(RelationshipType::class);
    }
}
