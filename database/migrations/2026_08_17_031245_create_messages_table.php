<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('conversation_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('parent_message_id')
                ->nullable()
                ->constrained('messages')
                ->nullOnDelete();

            $table->string('role', 20);

            $table->text('content');

            $table->jsonb('metadata')
                ->nullable();

            $table->unsignedInteger('token_count')
                ->nullable();

            $table->string('status', 30)
                ->default('completed');

            $table->timestamps();

            $table->index([
                'conversation_id',
                'created_at',
                'id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
