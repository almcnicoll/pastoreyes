<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('person_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->unique()->constrained()->cascadeOnDelete();
            $table->longText('data');                            // encrypted at app layer - base64 encoded image
            $table->string('mime_type');                         // encrypted at app layer
            $table->unsignedInteger('file_size');                // unencrypted - stored in bytes
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('person_photos');
    }
};
