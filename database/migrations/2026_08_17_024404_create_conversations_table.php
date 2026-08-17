<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_character_profile_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('title', 160);

            $table->text('summary')
                ->nullable();

            $table->timestamp('summary_updated_at')
                ->nullable();

            $table->timestamp('last_message_at')
                ->nullable();

            $table->timestamps();

            $table->index([
                'user_character_profile_id',
                'updated_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
