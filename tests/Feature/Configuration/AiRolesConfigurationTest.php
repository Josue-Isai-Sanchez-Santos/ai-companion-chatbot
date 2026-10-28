<?php

namespace Tests\Feature\Configuration;

use App\Ai\Configuration\AiRolesConfiguration;
use App\Ai\Contracts\ChatGateway;
use App\Ai\Contracts\EmbeddingGateway;
use App\Ai\Gateways\SimulatedChatGateway;
use App\Ai\Gateways\SimulatedEmbeddingGateway;
use LogicException;
use Tests\TestCase;

final class AiRolesConfigurationTest extends TestCase
{
    public function test_v2_has_safe_defaults(): void
    {
        $this->assertFalse(
            config('ai_roles.enabled')
        );

        $this->assertSame(
            'simulated',
            config('ai_roles.inference_mode')
        );

        $this->assertFalse(
            config('ai_roles.external_requests_allowed')
        );

        $this->assertSame(
            'simulated',
            config('ai_roles.director.driver')
        );

        $this->assertSame(
            'simulated',
            config('ai_roles.narrator.driver')
        );

        $this->assertSame(
            60,
            config('ai_roles.director.timeout')
        );

        $this->assertSame(
            120,
            config('ai_roles.narrator.timeout')
        );
    }

    public function test_disabled_v2_does_not_change_legacy_gateways(): void
    {
        config()->set(
            'ai_roles.enabled',
            false
        );

        /*
         * Even an incomplete v2 configuration
         * must not invalidate legacy operation
         * while the feature flag is disabled.
         */
        config()->set(
            'ai_roles.director.driver',
            'unsupported'
        );

        app(AiRolesConfiguration::class)
            ->validate();

        $this->assertInstanceOf(
            SimulatedChatGateway::class,
            app(ChatGateway::class)
        );

        $this->assertInstanceOf(
            SimulatedEmbeddingGateway::class,
            app(EmbeddingGateway::class)
        );
    }

    public function test_enabled_v2_accepts_simulated_roles(): void
    {
        config()->set(
            'ai_roles.enabled',
            true
        );

        $configuration = app(
            AiRolesConfiguration::class
        );

        $configuration->validate();

        $this->assertTrue(
            $configuration->isEnabled()
        );
    }

    public function test_enabled_flag_must_be_boolean(): void
    {
        config()->set(
            'ai_roles.enabled',
            'invalid'
        );

        $this->expectException(
            LogicException::class
        );

        $this->expectExceptionMessage(
            'AI v2 enabled flag must be boolean.'
        );

        app(AiRolesConfiguration::class)
            ->validate();
    }

    public function test_real_inference_mode_is_rejected(): void
    {
        config()->set(
            'ai_roles.enabled',
            true
        );

        config()->set(
            'ai_roles.inference_mode',
            'real'
        );

        $this->expectException(
            LogicException::class
        );

        app(AiRolesConfiguration::class)
            ->validate();
    }

    public function test_external_requests_are_rejected(): void
    {
        config()->set(
            'ai_roles.enabled',
            true
        );

        config()->set(
            'ai_roles.external_requests_allowed',
            true
        );

        $this->expectException(
            LogicException::class
        );

        app(AiRolesConfiguration::class)
            ->validate();
    }

    public function test_invalid_director_driver_is_rejected(): void
    {
        config()->set(
            'ai_roles.enabled',
            true
        );

        config()->set(
            'ai_roles.director.driver',
            'laravel'
        );

        $this->expectException(
            LogicException::class
        );

        $this->expectExceptionMessage(
            'AI role [director] must use the simulated driver.'
        );

        app(AiRolesConfiguration::class)
            ->validate();
    }

    public function test_invalid_narrator_driver_is_rejected(): void
    {
        config()->set(
            'ai_roles.enabled',
            true
        );

        config()->set(
            'ai_roles.narrator.driver',
            'laravel'
        );

        $this->expectException(
            LogicException::class
        );

        $this->expectExceptionMessage(
            'AI role [narrator] must use the simulated driver.'
        );

        app(AiRolesConfiguration::class)
            ->validate();
    }

    public function test_missing_role_configuration_is_rejected(): void
    {
        config()->set(
            'ai_roles.enabled',
            true
        );

        config()->set(
            'ai_roles.director',
            null
        );

        $this->expectException(
            LogicException::class
        );

        app(AiRolesConfiguration::class)
            ->validate();
    }

    public function test_invalid_director_timeout_is_rejected(): void
    {
        config()->set(
            'ai_roles.enabled',
            true
        );

        config()->set(
            'ai_roles.director.timeout',
            0
        );

        $this->expectException(
            LogicException::class
        );

        app(AiRolesConfiguration::class)
            ->validate();
    }

    public function test_invalid_narrator_timeout_is_rejected(): void
    {
        config()->set(
            'ai_roles.enabled',
            true
        );

        config()->set(
            'ai_roles.narrator.timeout',
            -1
        );

        $this->expectException(
            LogicException::class
        );

        app(AiRolesConfiguration::class)
            ->validate();
    }
}
