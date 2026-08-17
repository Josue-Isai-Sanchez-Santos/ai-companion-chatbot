<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_character_profiles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('character_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->jsonb('custom_personality')
                ->nullable();

            $table->jsonb('custom_speaking_style')
                ->nullable();

            $table->text('custom_scenario')
                ->nullable();

            $table->string('nickname_for_user', 120)
                ->nullable();

            $table->string('nickname_for_character', 120)
                ->nullable();

            $table->string('current_mood', 40);

            $table->foreignId('current_expression_id')
                ->nullable()
                ->constrained('character_expressions')
                ->nullOnDelete();

            $table->string('relationship_stage', 40);

            $table->unsignedSmallInteger('trust');
            $table->unsignedSmallInteger('affection');
            $table->unsignedSmallInteger('familiarity');
            $table->unsignedSmallInteger('tension');

            $table->timestamp('last_interaction_at')
                ->nullable();

            $table->timestamps();

            $table->unique([
                'user_id',
                'character_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_character_profiles');
    }
};
