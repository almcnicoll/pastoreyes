<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('relationships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained()->cascadeOnDelete();
            $table->foreignId('related_person_id')->constrained('persons')->cascadeOnDelete();
            $table->foreignId('relationship_type_id')->constrained()->restrictOnDelete();
            $table->string('notes')->nullable();                 // encrypted at app layer
            $table->date('date_from')->nullable();               // encrypted at app layer
            $table->date('date_to')->nullable();                 // encrypted at app layer
            $table->timestamps();

            // Prevent duplicate relationships between the same two people with the same type
            $table->unique(['person_id', 'related_person_id', 'relationship_type_id']);

            $table->index('user_id');
            $table->index('person_id');
            $table->index('related_person_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('relationships');
    }
};
