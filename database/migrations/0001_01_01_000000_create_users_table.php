<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');                        // encrypted at app layer
            $table->string('last_name');                         // encrypted at app layer
            $table->string('email')->unique();                   // unencrypted - used for lookups
            $table->string('google_oauth_id')->unique();         // unencrypted - Google's unique user ID
            $table->text('google_oauth_token')->nullable();      // encrypted - API access token
            $table->text('google_oauth_refresh_token')->nullable(); // encrypted - for token refresh
            $table->timestamp('google_token_expires_at')->nullable();
            $table->string('encryption_salt');                   // generated on first login, never changes
            $table->boolean('is_active')->default(true);
            $table->boolean('is_admin')->default(false);
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('users');
    }
};