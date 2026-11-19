<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Tables whose rows can be created through the API and so record where they came from. */
    private const PROVENANCE_TABLES = ['notes', 'prayer_needs', 'goals', 'outcomes', 'key_dates', 'tasks'];

    public function up(): void
    {
        Schema::create('api_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('token_hash', 64)->unique();   // SHA-256 of the token; the token itself is shown once
            $table->string('token_hint', 8);              // first characters, so a token can be recognised in the list
            $table->json('scopes');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->string('last_used_ip', 45)->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        // A summary only: method, path and a count-style description. Never request or response bodies.
        Schema::create('api_audit_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_token_id')->constrained('api_tokens')->cascadeOnDelete(); // goes with the token (and so with the account)
            $table->string('method', 10);
            $table->string('path');
            $table->unsignedSmallInteger('status');
            $table->string('summary')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['api_token_id', 'created_at']);
        });

        $this->createIdempotencyTable();

        foreach (self::PROVENANCE_TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->string('source', 20)->nullable();            // 'api' for rows an agent added
                $table->string('source_token_name')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (self::PROVENANCE_TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn(['source', 'source_token_name']);
            });
        }

        Schema::dropIfExists('api_idempotency_keys');
        Schema::dropIfExists('api_audit_log');
        Schema::dropIfExists('api_tokens');
    }

    private function createIdempotencyTable(): void
    {
        Schema::create('api_idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('api_token_id')->constrained('api_tokens')->cascadeOnDelete();
            $table->string('key', 100);
            $table->string('request_hash', 64);
            $table->unsignedSmallInteger('response_status');
            $table->longText('response');               // encrypted at app layer: it names people
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->unique(['api_token_id', 'key']);
        });
    }
};
