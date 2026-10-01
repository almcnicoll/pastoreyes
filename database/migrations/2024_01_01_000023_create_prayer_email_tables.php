<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // App-wide (non-user) settings, e.g. SMTP configuration
        Schema::create('app_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // Which weekday (ISO 1 = Monday .. 7 = Sunday) each person is emailed about
        Schema::create('prayer_email_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('persons')->cascadeOnDelete();
            $table->unsignedTinyInteger('day');
            $table->timestamps();

            $table->unique(['user_id', 'person_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prayer_email_assignments');
        Schema::dropIfExists('app_settings');
    }
};
