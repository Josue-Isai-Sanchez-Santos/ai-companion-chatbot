<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('character_expressions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('character_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('name', 80);

            $table->text('description')
                ->nullable();

            $table->string('image_path')
                ->nullable();

            $table->boolean('is_default')
                ->default(false);

            $table->timestamps();

            $table->unique([
                'character_id',
                'name',
            ]);
        });

        DB::statement(
            'CREATE UNIQUE INDEX character_expressions_one_default_per_character
             ON character_expressions (character_id)
             WHERE is_default = true'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('character_expressions');
    }
};
