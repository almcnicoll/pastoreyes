<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |----------------------------------------------------------------------
        | contact_sync_state
        |----------------------------------------------------------------------
        | Tracks where the batch sync got to for each user, so it can resume
        | from the correct position on the next run.
        |----------------------------------------------------------------------
        */
        Schema::create('contact_sync_state', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            // The ID of the last Person record processed in the previous batch.
            // NULL means start from the beginning.
            $table->unsignedBigInteger('last_person_id')->nullable();

            // When the last batch ran
            $table->timestamp('last_run_at')->nullable();

            // How many persons were processed in the last batch
            $table->unsignedInteger('last_batch_size')->default(0);

            $table->timestamps();
        });

        /*
        |----------------------------------------------------------------------
        | contact_sync_reviews
        |----------------------------------------------------------------------
        | One row per differing field per person. Each row represents a
        | single flagged difference that the user needs to resolve.
        |----------------------------------------------------------------------
        */
        Schema::create('contact_sync_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('persons')->cascadeOnDelete();

            // Which field is different
            $table->string('field'); // e.g. 'first_name', 'birthday', 'photo', 'address'

            // Human-readable label for display
            $table->string('field_label');

            // The value currently stored in PastorEyes (plaintext for display — encrypted at rest)
            $table->text('local_value')->nullable();   // encrypted

            // The value found in Google Contacts (plaintext for display — encrypted at rest)
            $table->text('google_value')->nullable();  // encrypted

            // Resolution status
            $table->enum('status', [
                'pending',    // not yet resolved
                'pushed_to_google',     // user chose to push local to Google
                'pulled_to_local',      // user chose to pull Google to PastorEyes
                'ignored',    // user chose to ignore this difference
            ])->default('pending');

            // When this difference was first detected
            $table->timestamp('detected_at');

            // When it was resolved (if at all)
            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['person_id', 'field']);

            // Prevent duplicate pending reviews for the same person+field
            $table->unique(['person_id', 'field', 'status'], 'sync_reviews_person_field_status_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_sync_reviews');
        Schema::dropIfExists('contact_sync_state');
    }
};