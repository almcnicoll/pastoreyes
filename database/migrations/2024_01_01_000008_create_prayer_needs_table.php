<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prayer_needs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');                                // unencrypted - when prayer need arose
            $table->timestamp('logged_at');                      // unencrypted - when record was entered
            $table->string('title')->nullable();                 // encrypted at app layer
            $table->longText('body');                            // encrypted at app layer
            $table->date('resolved_at')->nullable();             // unencrypted
            $table->longText('resolution_details')->nullable();  // encrypted at app layer
            $table->unsignedTinyInteger('significance');         // unencrypted (1-5)
            $table->timestamps();

            $table->index(['user_id', 'date']);
            $table->index(['user_id', 'resolved_at']);
            $table->index(['user_id', 'significance']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prayer_needs');
    }
};
