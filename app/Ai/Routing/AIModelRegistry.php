<?php

namespace App\Ai\Routing;

use App\Ai\DTOs\AIModelDescriptor;
use LogicException;

final class AIModelRegistry
{
    private const ROLE_CONFIG = [
        'director' => 'ai_roles.director',
        'narrator' => 'ai_roles.narrator',
        'embedding' => 'ai.embedding',
        'legacy_chat' => 'ai.chat',
    ];

    /**
     * Return descriptors for all registered roles.
     *
     * @return array<string, AIModelDescriptor>
     */
    public function all(): array
    {
        $result = [];

        foreach (array_keys(self::ROLE_CONFIG) as $role) {
            $result[$role] = $this->get($role);
        }

        return $result;
    }

    /**
     * Read and validate one role configuration.
     *
     * This method never performs inference.
     */
    public function get(string $role): AIModelDescriptor
    {
        $path = self::ROLE_CONFIG[$role] ?? null;

        if ($path === null) {
            throw new LogicException(
                "Unknown AI role [{$role}]."
            );
        }

        $options = config($path);

        if (! is_array($options)) {
            throw new LogicException(
                "AI role [{$role}] configuration section is missing."
            );
        }

        $driver = $this->optionalString(
            $options['driver'] ?? null,
            $role,
            'driver'
        ) ?? AIModelDescriptor::UNCONFIGURED;

        if (! in_array(
            $driver,
            [
                'simulated',
                'laravel',
                AIModelDescriptor::UNCONFIGURED,
            ],
            true
        )) {
            throw new LogicException(
                "AI role [{$role}] has an unsupported driver."
            );
        }

        $provider = $this->optionalString(
            $options['provider'] ?? null,
            $role,
            'provider'
        );

        $model = $this->optionalString(
            $options['model'] ?? null,
            $role,
            'model'
        );

        $timeout = $options['timeout'] ?? null;

        if (! is_int($timeout) || $timeout < 1) {
            throw new LogicException(
                "AI role [{$role}] requires a positive integer timeout."
            );
        }

        $v2Role = in_array(
            $role,
            ['director', 'narrator'],
            true
        );

        $contextWindow = $this->contextWindow(
            $options['context_window'] ?? null,
            $role
        );

        $streaming = $this->capability(
            $v2Role
                ? ($options['supports_streaming'] ?? null)
                : ($role === 'legacy_chat'),
            $role,
            'supports_streaming'
        );

        $structured = $this->capability(
            $v2Role
                ? ($options['supports_structured_output'] ?? null)
                : false,
            $role,
            'supports_structured_output'
        );

        $cancellation = $this->capability(
            $v2Role
                ? ($options['supports_cancellation'] ?? null)
                : false,
            $role,
            'supports_cancellation'
        );

        $format = $v2Role
            ? ($options['output_format'] ?? null)
            : ($role === 'embedding' ? 'vector' : 'text');

        if (
            ! is_string($format)
            || ! in_array(
                $format,
                ['json', 'segments', 'text', 'vector'],
                true
            )
        ) {
            throw new LogicException(
                "AI role [{$role}] has an invalid output format."
            );
        }

        $status = match (true) {
            $driver === 'simulated' => AIModelDescriptor::SIMULATED,

            $driver === 'laravel'
                && $provider !== null
                && $model !== null => AIModelDescriptor::CONFIGURED,

            default => AIModelDescriptor::UNCONFIGURED,
        };

        /*
         * A simulated role does not use a real
         * provider or model, even if optional
         * future values have been configured.
         */
        if ($status === AIModelDescriptor::SIMULATED) {
            $provider = null;
            $model = null;
        }

        return new AIModelDescriptor(
            role: $role,
            driver: $driver,
            provider: $provider,
            model: $model,
            timeout: $timeout,
            contextWindow: $contextWindow,
            supportsStreaming: $streaming,
            supportsStructuredOutput: $structured,
            outputFormat: $format,
            supportsCancellation: $cancellation,
            status: $status,
        );
    }

    /**
     * Require a role to be configured or simulated.
     *
     * This checks metadata only; it does not
     * authorize execution of a real model.
     */
    public function requireConfigured(
        string $role
    ): AIModelDescriptor {
        $descriptor = $this->get($role);

        if (
            $descriptor->status
            === AIModelDescriptor::UNCONFIGURED
        ) {
            throw new LogicException(
                "AI role [{$role}] is unconfigured. "
                .'Check driver, provider and model settings.'
            );
        }

        return $descriptor;
    }

    private function optionalString(
        mixed $value,
        string $role,
        string $field
    ): ?string {
        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            throw new LogicException(
                "AI role [{$role}] has an invalid [{$field}] value."
            );
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function capability(
        mixed $value,
        string $role,
        string $field
    ): bool {
        if (! is_bool($value)) {
            throw new LogicException(
                "AI role [{$role}] requires a boolean [{$field}]."
            );
        }

        return $value;
    }

    private function contextWindow(
        mixed $value,
        string $role
    ): ?int {
        if ($value === null || $value === '') {
            return null;
        }

        /*
         * Environment variables can contain
         * positive integers represented as strings.
         */
        if (is_string($value) && ctype_digit($value)) {
            $value = filter_var(
                $value,
                FILTER_VALIDATE_INT
            );
        }

        if (! is_int($value) || $value < 1) {
            throw new LogicException(
                "AI role [{$role}] requires a "
                .'positive context window or null.'
            );
        }

        return $value;
    }
}
