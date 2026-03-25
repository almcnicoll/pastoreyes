<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('persons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('google_contact_id')->nullable();     // unencrypted - external reference only
            $table->enum('gender', ['male', 'female', 'unknown'])->nullable();
            $table->date('date_of_birth')->nullable();           // encrypted at app layer
            $table->boolean('dob_year_unknown')->default(false);
            $table->date('date_of_death')->nullable();           // encrypted at app layer
            $table->longText('notes')->nullable();               // encrypted at app layer
            $table->timestamps();

            $table->index(['user_id', 'google_contact_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('persons');
    }
};
