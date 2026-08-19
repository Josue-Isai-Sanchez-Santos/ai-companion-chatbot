<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memories', function (Blueprint $table) {
            $table->id();

            $table->foreignId(
                'user_character_profile_id'
            )
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId(
                'source_message_id'
            )
                ->nullable()
                ->constrained('messages')
                ->nullOnDelete();

            $table->string(
                'type',
                50
            );

            $table->text(
                'content'
            );

            $table->decimal(
                'importance',
                5,
                4
            )->default(0.5000);

            $table->decimal(
                'confidence',
                5,
                4
            )->default(1.0000);

            $table->vector(
                'embedding',
                dimensions: 1536
            )->nullable();

            $table->unsignedBigInteger(
                'access_count'
            )->default(0);

            $table->timestamp(
                'last_accessed_at'
            )->nullable();

            $table->timestamp(
                'expires_at'
            )->nullable();

            $table->timestamps();

            $table->index([
                'user_character_profile_id',
                'type',
            ]);

            $table->index([
                'user_character_profile_id',
                'expires_at',
            ]);
        });

        DB::statement(
            <<<'SQL'
            ALTER TABLE memories
            ADD CONSTRAINT memories_importance_check
            CHECK (
                importance >= 0
                AND importance <= 1
            )
            SQL
        );

        DB::statement(
            <<<'SQL'
            ALTER TABLE memories
            ADD CONSTRAINT memories_confidence_check
            CHECK (
                confidence >= 0
                AND confidence <= 1
            )
            SQL
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'memories'
        );
    }
};
