<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop Laravel system tables. Keeps users and game_systems (Supabase Postgres).
     * Session/cache/queue use file/cookie/sync.
     */
    public function up(): void
    {
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
        Schema::dropIfExists('sessions');
    }

    public function down(): void
    {
        // Cannot recreate dropped tables; run migrate:fresh to restore.
    }
};
