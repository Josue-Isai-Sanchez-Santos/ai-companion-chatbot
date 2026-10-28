<?php

namespace App\Ai\Configuration;

use LogicException;

final class AiRolesConfiguration
{
    private const ROLES = [
        'director',
        'narrator',
    ];

    /**
     * Determine whether the v2 architecture
     * has been explicitly enabled.
     */
    public function isEnabled(): bool
    {
        return config(
            'ai_roles.enabled'
        ) === true;
    }

    /**
     * Validate the current v2 configuration.
     *
     * During the simulation-only development
     * phase, no real inference driver is allowed.
     *
     * This validates configuration values.
     * Enforcement at the execution boundary
     * will be implemented in later steps.
     */
    public function validate(): void
    {
        $enabled = config(
            'ai_roles.enabled'
        );

        if (! is_bool($enabled)) {
            throw new LogicException(
                'AI v2 enabled flag must be boolean.'
            );
        }

        /*
         * When v2 is disabled, its configuration
         * must not interfere with legacy v1.
         */
        if (! $enabled) {
            return;
        }

        $this->validateInferenceMode();

        $this->validateExternalRequests();

        foreach (self::ROLES as $role) {
            $this->validateRole($role);
        }
    }

    private function validateInferenceMode(): void
    {
        $mode = config(
            'ai_roles.inference_mode'
        );

        if ($mode !== 'simulated') {
            throw new LogicException(
                'AI v2 only supports simulated inference.'
            );
        }
    }

    private function validateExternalRequests(): void
    {
        $allowed = config(
            'ai_roles.external_requests_allowed'
        );

        if ($allowed !== false) {
            throw new LogicException(
                'External AI requests are not allowed in v2 simulation mode.'
            );
        }
    }

    private function validateRole(
        string $role
    ): void {
        $configuration = config(
            "ai_roles.{$role}"
        );

        if (! is_array($configuration)) {
            throw new LogicException(
                "AI role [{$role}] configuration is missing."
            );
        }

        $driver = $configuration['driver']
            ?? null;

        if ($driver !== 'simulated') {
            throw new LogicException(
                "AI role [{$role}] must use the simulated driver."
            );
        }

        $timeout = $configuration['timeout']
            ?? null;

        if (
            ! is_int($timeout)
            || $timeout < 1
        ) {
            throw new LogicException(
                "AI role [{$role}] has an invalid timeout."
            );
        }
    }
}
