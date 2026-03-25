<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained()->cascadeOnDelete();
            $table->string('line_1')->nullable();                // encrypted at app layer
            $table->string('line_2')->nullable();                // encrypted at app layer
            $table->string('line_3')->nullable();                // encrypted at app layer
            $table->string('city')->nullable();                  // encrypted at app layer
            $table->string('county')->nullable();                // encrypted at app layer
            $table->string('postcode')->nullable();              // encrypted at app layer
            $table->string('country')->nullable();               // encrypted at app layer
            $table->date('date_added');                          // encrypted at app layer
            $table->boolean('is_current')->default(true);
            $table->string('notes')->nullable();                 // encrypted at app layer
            $table->timestamps();

            $table->index(['person_id', 'is_current']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
