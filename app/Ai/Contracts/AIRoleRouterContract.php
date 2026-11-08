<?php

namespace App\Ai\Contracts;

use App\Ai\DTOs\AIModelDescriptor;

interface AIRoleRouterContract
{
    /**
     * Resolve an AI role by its identifier.
     *
     * This method does not perform inference.
     */
    public function forRole(
        string $role
    ): AIModelDescriptor;

    /**
     * Resolve the AI role assigned to a task.
     *
     * This method does not perform inference.
     */
    public function forTask(
        string $task
    ): AIModelDescriptor;
}
