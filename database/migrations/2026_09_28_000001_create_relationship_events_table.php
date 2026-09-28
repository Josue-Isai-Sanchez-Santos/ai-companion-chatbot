<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'relationship_events',
            function (
                Blueprint $table
            ): void {
                $table->id();

                $table
                    ->foreignId(
                        'user_character_profile_id'
                    )
                    ->constrained()
                    ->cascadeOnDelete();

                $table
                    ->foreignId(
                        'conversation_id'
                    )
                    ->nullable()
                    ->constrained()
                    ->nullOnDelete();

                $table
                    ->foreignId(
                        'user_message_id'
                    )
                    ->nullable()
                    ->constrained(
                        'messages'
                    )
                    ->nullOnDelete();

                $table
                    ->foreignId(
                        'assistant_message_id'
                    )
                    ->nullable()
                    ->constrained(
                        'messages'
                    )
                    ->nullOnDelete();

                /*
                 * One completed assistant turn may
                 * alter the relationship at most once.
                 */
                $table->unique(
                    'assistant_message_id'
                );

                $table->string(
                    'event_summary',
                    500
                );

                $table->string(
                    'from_stage',
                    40
                );

                $table->string(
                    'to_stage',
                    40
                );

                foreach ([
                    'trust',
                    'affection',
                    'familiarity',
                    'tension',
                ] as $metric) {
                    $table->smallInteger(
                        "requested_{$metric}_delta"
                    );

                    $table->smallInteger(
                        "applied_{$metric}_delta"
                    );

                    $table->unsignedSmallInteger(
                        "{$metric}_before"
                    );

                    $table->unsignedSmallInteger(
                        "{$metric}_after"
                    );
                }

                $table->timestamps();

                $table->index([
                    'user_character_profile_id',
                    'created_at',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'relationship_events'
        );
    }
};
