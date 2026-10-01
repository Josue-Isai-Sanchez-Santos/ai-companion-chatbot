<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'reset_audits',
            function (
                Blueprint $table
            ): void {
                $table->id();

                $table
                    ->foreignId('user_id')
                    ->constrained()
                    ->cascadeOnDelete();

                $table
                    ->foreignId('character_id')
                    ->constrained()
                    ->cascadeOnDelete();

                /*
                 * These are intentionally not foreign
                 * keys. The previous profile has been
                 * deleted and the replacement may also
                 * be reset in the future.
                 */
                $table->unsignedBigInteger(
                    'previous_profile_id'
                );

                $table->unsignedBigInteger(
                    'new_profile_id'
                );

                $table->unsignedInteger(
                    'deleted_conversations'
                )->default(0);

                $table->unsignedInteger(
                    'deleted_messages'
                )->default(0);

                $table->unsignedInteger(
                    'deleted_memories'
                )->default(0);

                $table->unsignedInteger(
                    'deleted_relationship_events'
                )->default(0);

                $table->timestamp(
                    'reset_at'
                );

                $table->index([
                    'user_id',
                    'character_id',
                    'reset_at',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'reset_audits'
        );
    }
};
