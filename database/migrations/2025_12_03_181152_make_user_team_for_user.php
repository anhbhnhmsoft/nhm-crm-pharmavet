<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('user_team')) {
            Schema::create('user_team', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreignId('team_id')->references('id')->on('teams')->onDelete('cascade');
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Compatibility migration: keep schema from baseline init migration.
    }
};
