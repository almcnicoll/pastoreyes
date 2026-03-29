<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Date columns that are encrypted at the application layer cannot be stored
     * as MySQL DATE columns — they must be TEXT to hold the encrypted string.
     */
    public function up(): void
    {
        Schema::table('persons', function (Blueprint $table) {
            $table->text('date_of_birth')->nullable()->change();
            $table->text('date_of_death')->nullable()->change();
        });

        // Also fix encrypted date columns on person_names
        Schema::table('person_names', function (Blueprint $table) {
            $table->text('date_from')->nullable()->change();
            $table->text('date_to')->nullable()->change();
        });

        // And on relationships
        Schema::table('relationships', function (Blueprint $table) {
            $table->text('date_from')->nullable()->change();
            $table->text('date_to')->nullable()->change();
        });

        // And on addresses
        Schema::table('addresses', function (Blueprint $table) {
            $table->text('date_added')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('persons', function (Blueprint $table) {
            $table->date('date_of_birth')->nullable()->change();
            $table->date('date_of_death')->nullable()->change();
        });

        Schema::table('person_names', function (Blueprint $table) {
            $table->date('date_from')->nullable()->change();
            $table->date('date_to')->nullable()->change();
        });

        Schema::table('relationships', function (Blueprint $table) {
            $table->date('date_from')->nullable()->change();
            $table->date('date_to')->nullable()->change();
        });

        Schema::table('addresses', function (Blueprint $table) {
            $table->date('date_added')->nullable()->change();
        });
    }
};
