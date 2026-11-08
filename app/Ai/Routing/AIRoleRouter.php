<?php

namespace App\Ai\Routing;

use App\Ai\Configuration\AiRolesConfiguration;
use App\Ai\Contracts\AIRoleRouterContract;
use App\Ai\DTOs\AIModelDescriptor;
use LogicException;

final class AIRoleRouter implements AIRoleRouterContract
{
    /*
     * Roles supported by the application.
     */
    private const ROLES = [
        'director',
        'narrator',
        'embedding',
        'legacy_chat',
    ];

    /*
     * Roles reserved for the v2 architecture.
     */
    private const V2_ROLES = [
        'director',
        'narrator',
    ];

    /*
     * Explicit mapping between application
     * tasks and AI roles.
     *
     * No model or provider is hardcoded here.
     */
    private const TASK_ROLE = [
        'scene_direction' => 'director',
        'narrative_generation' => 'narrator',
        'memory_embedding' => 'embedding',
        'legacy_message' => 'legacy_chat',
    ];

    public function __construct(
        private readonly AIModelRegistry $registry,
        private readonly AiRolesConfiguration $configuration,
    ) {}

    /**
     * Resolve a registered role.
     *
     * Validates configuration and feature flags,
     * then returns model metadata.
     *
     * No external service is contacted.
     */
    public function forRole(
        string $role
    ): AIModelDescriptor {
        if (! in_array($role, self::ROLES, true)) {
            throw new LogicException(
                "Unknown AI role [{$role}]."
            );
        }

        /*
         * Validate the current v2 configuration.
         *
         * When v2 is disabled, this preserves
         * legacy compatibility.
         */
        $this->configuration->validate();

        /*
         * Director and Narrator require explicit
         * activation of the v2 architecture.
         */
        if (
            in_array($role, self::V2_ROLES, true)
            && ! $this->configuration->isEnabled()
        ) {
            throw new LogicException(
                'AI v2 is disabled. '
                ."Role [{$role}] cannot be routed."
            );
        }

        /*
         * Only return a validated descriptor.
         *
         * This does not instantiate or execute
         * an inference gateway.
         */
        return $this->registry->requireConfigured(
            $role
        );
    }

    /**
     * Resolve the role assigned to a task.
     *
     * Unknown tasks are rejected rather than
     * redirected to another model.
     */
    public function forTask(
        string $task
    ): AIModelDescriptor {
        $role = self::TASK_ROLE[$task] ?? null;

        if ($role === null) {
            throw new LogicException(
                "Unknown AI task [{$task}]."
            );
        }

        return $this->forRole($role);
    }
}
