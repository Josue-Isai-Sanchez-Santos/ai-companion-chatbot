<?php

namespace App\Actions\Memories;

use App\Models\Memory;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class DeleteMemoryAction
{
    public function execute(
        User $user,
        Memory $memory
    ): void {
        Gate::forUser($user)->authorize(
            'delete',
            $memory
        );

        $memory->delete();
    }
}
