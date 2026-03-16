<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Matches Python app/dbmodels/user_db.py: uuid (PK), username, created_at, updated_at
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->string('uuid', 36)->primary();
            $table->string('username');
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
