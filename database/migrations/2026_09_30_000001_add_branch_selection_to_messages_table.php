<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'messages',
            function (Blueprint $table): void {
                /*
                 * Existing conversations are linear,
                 * so every existing message starts as
                 * part of the selected branch.
                 */
                $table->boolean(
                    'is_active_branch'
                )->default(true);

                $table->index(
                    [
                        'conversation_id',
                        'is_active_branch',
                        'created_at',
                        'id',
                    ],
                    'messages_active_branch_lookup'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'messages',
            function (Blueprint $table): void {
                $table->dropIndex(
                    'messages_active_branch_lookup'
                );

                $table->dropColumn(
                    'is_active_branch'
                );
            }
        );
    }
};
