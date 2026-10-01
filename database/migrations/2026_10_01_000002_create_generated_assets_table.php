<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'generated_assets',
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

                $table->string(
                    'type',
                    40
                );

                /*
                 * Only the relative storage path is
                 * persisted. Never an absolute server
                 * filesystem path.
                 */
                $table->string(
                    'path',
                    500
                )->unique();

                $table->string(
                    'mime_type',
                    120
                )->nullable();

                $table->unsignedBigInteger(
                    'size_bytes'
                )->nullable();

                $table->timestamps();

                $table->index([
                    'user_character_profile_id',
                    'type',
                ]);
            }
        );

        /*
         * Defense in depth:
         *
         * every persisted path must live below the
         * directory belonging to its own profile.
         */
        DB::statement(
            <<<'SQL'
            ALTER TABLE generated_assets
            ADD CONSTRAINT generated_assets_safe_path_check
            CHECK (
                path LIKE
                    'character-assets/profiles/'
                    || user_character_profile_id
                    || '/%'
                AND path NOT LIKE '%..%'
            )
            SQL
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'generated_assets'
        );
    }
};
