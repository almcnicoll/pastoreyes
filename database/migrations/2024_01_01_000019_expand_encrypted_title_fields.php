<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Expand the encrypted title/label fields on timeline entry tables to string(700).
 *
 * These fields store AES-256-CBC ciphertext via EncryptedCast (base64-encoded JSON
 * containing IV + ciphertext + HMAC-SHA256 MAC). The storage overhead is roughly
 * 2.7× the plaintext length plus ~114 chars of fixed overhead, meaning a 200-char
 * plaintext requires ~537 chars of storage. string(700) comfortably accommodates
 * plaintexts up to ~290 characters.
 *
 * Affected fields:
 *   notes.title
 *   prayer_needs.title
 *   goals.title
 *   outcomes.title
 *   key_dates.label
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->string('title', 700)->nullable()->change();
        });

        Schema::table('prayer_needs', function (Blueprint $table) {
            $table->string('title', 700)->nullable()->change();
        });

        Schema::table('goals', function (Blueprint $table) {
            $table->string('title', 700)->change();
        });

        Schema::table('outcomes', function (Blueprint $table) {
            $table->string('title', 700)->change();
        });

        Schema::table('key_dates', function (Blueprint $table) {
            $table->string('label', 700)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->string('title', 255)->nullable()->change();
        });

        Schema::table('prayer_needs', function (Blueprint $table) {
            $table->string('title', 255)->nullable()->change();
        });

        Schema::table('goals', function (Blueprint $table) {
            $table->string('title', 255)->change();
        });

        Schema::table('outcomes', function (Blueprint $table) {
            $table->string('title', 255)->change();
        });

        Schema::table('key_dates', function (Blueprint $table) {
            $table->string('label', 255)->nullable()->change();
        });
    }
};