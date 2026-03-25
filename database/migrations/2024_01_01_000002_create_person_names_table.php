<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('person_names', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained()->cascadeOnDelete();
            $table->string('first_name')->nullable();            // encrypted at app layer
            $table->string('last_name')->nullable();             // encrypted at app layer
            $table->string('middle_names')->nullable();          // encrypted at app layer
            $table->string('preferred_name')->nullable();        // encrypted at app layer
            $table->enum('type', ['birth', 'married', 'preferred', 'other']);  // unencrypted - for filtering
            $table->boolean('spelling_uncertain')->default(false);
            $table->date('date_from')->nullable();               // encrypted at app layer
            $table->date('date_to')->nullable();                 // encrypted at app layer
            $table->boolean('is_primary')->default(false);
            $table->string('notes')->nullable();                 // encrypted at app layer
            $table->timestamps();

            $table->index(['person_id', 'is_primary']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('person_names');
    }
};
