<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per difference the user has chosen to ignore. The hash is a keyed
        // digest of the field + both values, so a changed value is a new difference.
        Schema::create('ignored_sync_differences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('persons')->cascadeOnDelete();
            $table->string('field');
            $table->string('hash', 64);
            $table->timestamps();

            $table->unique(['person_id', 'field', 'hash'], 'ignored_sync_person_field_hash_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ignored_sync_differences');
    }
};
