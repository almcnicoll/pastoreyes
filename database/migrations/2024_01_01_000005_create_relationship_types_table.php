<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('relationship_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // null = global preset
            $table->string('name');                              // unencrypted - category label, not personal data
            $table->string('inverse_name')->nullable();          // unencrypted
            $table->boolean('is_directional')->default(false);
            $table->boolean('is_preset')->default(false);
            $table->timestamps();

            $table->index('user_id');
            $table->index('is_preset');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('relationship_types');
    }
};
