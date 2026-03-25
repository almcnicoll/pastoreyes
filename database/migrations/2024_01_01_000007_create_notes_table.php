<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');                                // unencrypted - for sorting/filtering
            $table->timestamp('logged_at');                      // unencrypted - when record was entered
            $table->string('title')->nullable();                 // encrypted at app layer
            $table->longText('body');                            // encrypted at app layer
            $table->unsignedTinyInteger('significance');         // unencrypted - for sorting/filtering (1-5)
            $table->timestamps();

            $table->index(['user_id', 'date']);
            $table->index(['user_id', 'significance']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notes');
    }
};
