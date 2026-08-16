<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('characters', function (Blueprint $table) {
            $table->id();

            $table->string('name', 120);
            $table->string('slug', 140)->unique();

            $table->text('description')->nullable();

            $table->jsonb('base_personality');
            $table->text('base_backstory');
            $table->jsonb('base_speaking_style');
            $table->text('base_scenario');
            $table->text('system_rules');
            $table->text('initial_message');

            $table->string('avatar_path')->nullable();

            $table->boolean('is_active')
                ->default(true)
                ->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('characters');
    }
};
