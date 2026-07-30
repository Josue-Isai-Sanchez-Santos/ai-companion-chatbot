<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Enable pgvector in the current PostgreSQL database.
     */
    public function up(): void
    {
        Schema::ensureVectorExtensionExists();
    }

    /**
     * Disable pgvector when this migration is rolled back.
     */
    public function down(): void
    {
        DB::statement('DROP EXTENSION IF EXISTS vector');
    }
};
