<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');                         // encrypted at app layer
            $table->longText('narrative')->nullable();       // encrypted at app layer
            $table->date('due_date');                        // unencrypted — for sorting/filtering
            $table->boolean('is_complete')->default(false);
            $table->timestamp('logged_at');                  // unencrypted
            $table->timestamps();

            $table->index(['user_id', 'due_date']);
            $table->index(['user_id', 'is_complete']);
        });

        Schema::create('person_task', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('persons')->cascadeOnDelete();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['person_id', 'task_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('person_task');
        Schema::dropIfExists('tasks');
    }
};
