<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('key_dates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');                                // unencrypted - the date itself
            $table->boolean('year_unknown')->default(false);
            $table->timestamp('logged_at');                      // unencrypted
            $table->enum('type', [                               // unencrypted - for filtering
                'birthday',
                'wedding_anniversary',
                'bereavement',
                'other'
            ]);
            $table->string('label')->nullable();                 // encrypted at app layer
            $table->boolean('is_recurring')->default(true);
            $table->unsignedTinyInteger('significance');         // unencrypted (1-5)
            $table->string('google_calendar_event_id')->nullable(); // unencrypted - external reference
            $table->string('google_calendar_id')->nullable();    // unencrypted - which calendar it's synced to
            $table->timestamps();

            $table->index(['user_id', 'date']);
            $table->index(['user_id', 'type']);
            $table->index(['user_id', 'is_recurring']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('key_dates');
    }
};
