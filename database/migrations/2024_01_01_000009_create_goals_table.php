<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');                                // unencrypted - when goal was set
            $table->timestamp('logged_at');                      // unencrypted - when record was entered
            $table->string('title');                             // encrypted at app layer
            $table->longText('body');                            // encrypted at app layer
            $table->date('target_date')->nullable();             // unencrypted - for sorting/filtering
            $table->date('achieved_at')->nullable();             // unencrypted
            $table->unsignedTinyInteger('significance');         // unencrypted (1-5)
            $table->timestamps();

            $table->index(['user_id', 'date']);
            $table->index(['user_id', 'target_date']);
            $table->index(['user_id', 'achieved_at']);
            $table->index(['user_id', 'significance']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goals');
    }
};
