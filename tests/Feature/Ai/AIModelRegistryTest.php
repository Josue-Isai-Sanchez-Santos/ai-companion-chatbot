<?php

namespace Tests\Feature\Ai;

use App\Ai\DTOs\AIModelDescriptor;
use App\Ai\Routing\AIModelRegistry;
use LogicException;
use Tests\TestCase;

final class AIModelRegistryTest extends TestCase
{
    public function test_registry_lists_four_roles(): void
    {
        $descriptors = app(AIModelRegistry::class)->all();

        $this->assertSame(
            ['director', 'narrator', 'embedding', 'legacy_chat'],
            array_keys($descriptors)
        );
    }

    public function test_director_has_simulated_configuration_and_capabilities(): void
    {
        $director = app(AIModelRegistry::class)->get('director');

        $this->assertSame(AIModelDescriptor::SIMULATED, $director->status);
        $this->assertSame('simulated', $director->driver);
        $this->assertNull($director->provider);
        $this->assertNull($director->model);
        $this->assertSame(60, $director->timeout);
        $this->assertNull($director->contextWindow);
        $this->assertFalse($director->supportsStreaming);
        $this->assertTrue($director->supportsStructuredOutput);
        $this->assertSame('json', $director->outputFormat);
        $this->assertFalse($director->supportsCancellation);
    }

    public function test_narrator_has_independent_capabilities(): void
    {
        $narrator = app(AIModelRegistry::class)->get('narrator');

        $this->assertSame(AIModelDescriptor::SIMULATED, $narrator->status);
        $this->assertSame(120, $narrator->timeout);
        $this->assertTrue($narrator->supportsStreaming);
        $this->assertTrue($narrator->supportsStructuredOutput);
        $this->assertSame('segments', $narrator->outputFormat);
        $this->assertFalse($narrator->supportsCancellation);
    }

    public function test_legacy_roles_preserve_original_configuration(): void
    {
        $registry = app(AIModelRegistry::class);

        $chat = $registry->get('legacy_chat');
        $embedding = $registry->get('embedding');

        $this->assertSame(config('ai.chat.driver'), $chat->driver);
        $this->assertSame(config('ai.chat.timeout'), $chat->timeout);
        $this->assertTrue($chat->supportsStreaming);
        $this->assertSame('text', $chat->outputFormat);

        $this->assertSame(config('ai.embedding.driver'), $embedding->driver);
        $this->assertSame(config('ai.embedding.timeout'), $embedding->timeout);
        $this->assertFalse($embedding->supportsStreaming);
        $this->assertSame('vector', $embedding->outputFormat);
    }

    public function test_missing_model_creates_unconfigured_state(): void
    {
        config()->set('ai_roles.director.driver', 'laravel');
        config()->set('ai_roles.director.provider', 'ollama');
        config()->set('ai_roles.director.model', null);

        $director = app(AIModelRegistry::class)->get('director');

        $this->assertSame(
            AIModelDescriptor::UNCONFIGURED,
            $director->status
        );

        $this->assertNull($director->model);
    }

    public function test_missing_driver_creates_unconfigured_state(): void
    {
        config()->set('ai_roles.director.driver', null);

        $this->assertSame(
            AIModelDescriptor::UNCONFIGURED,
            app(AIModelRegistry::class)->get('director')->status
        );
    }

    public function test_require_configured_explains_missing_configuration(): void
    {
        config()->set('ai_roles.director.driver', 'laravel');
        config()->set('ai_roles.director.provider', 'ollama');
        config()->set('ai_roles.director.model', null);

        $this->expectException(LogicException::class);

        $this->expectExceptionMessage(
            'AI role [director] is unconfigured. Check driver, provider and model settings.'
        );

        app(AIModelRegistry::class)->requireConfigured('director');
    }

    public function test_simulated_role_counts_as_configured_for_simulation(): void
    {
        $director = app(AIModelRegistry::class)
            ->requireConfigured('director');

        $this->assertSame(
            AIModelDescriptor::SIMULATED,
            $director->status
        );
    }

    public function test_complete_legacy_provider_configuration_is_described_not_executed(): void
    {
        config()->set('ai.chat.driver', 'laravel');
        config()->set('ai.chat.provider', 'ollama');
        config()->set('ai.chat.model', 'future-model');

        $chat = app(AIModelRegistry::class)->get('legacy_chat');

        $this->assertSame(AIModelDescriptor::CONFIGURED, $chat->status);
        $this->assertSame('ollama', $chat->provider);
        $this->assertSame('future-model', $chat->model);
    }

    public function test_unknown_role_is_rejected(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Unknown AI role [unknown].');

        app(AIModelRegistry::class)->get('unknown');
    }

    public function test_unsupported_driver_is_rejected(): void
    {
        config()->set('ai_roles.director.driver', 'unexpected');

        $this->expectException(LogicException::class);

        $this->expectExceptionMessage(
            'AI role [director] has an unsupported driver.'
        );

        app(AIModelRegistry::class)->get('director');
    }

    public function test_invalid_timeout_is_rejected(): void
    {
        config()->set('ai_roles.narrator.timeout', 0);

        $this->expectException(LogicException::class);

        $this->expectExceptionMessage(
            'AI role [narrator] requires a positive integer timeout.'
        );

        app(AIModelRegistry::class)->get('narrator');
    }

    public function test_context_window_accepts_numeric_environment_value(): void
    {
        config()->set('ai_roles.director.context_window', '16384');

        $this->assertSame(
            16384,
            app(AIModelRegistry::class)->get('director')->contextWindow
        );
    }

    public function test_invalid_context_window_is_rejected(): void
    {
        config()->set('ai_roles.director.context_window', 'many');

        $this->expectException(LogicException::class);

        $this->expectExceptionMessage(
            'AI role [director] requires a positive context window or null.'
        );

        app(AIModelRegistry::class)->get('director');
    }

    public function test_invalid_capability_type_is_rejected(): void
    {
        config()->set('ai_roles.narrator.supports_streaming', 'yes');

        $this->expectException(LogicException::class);

        $this->expectExceptionMessage(
            'AI role [narrator] requires a boolean [supports_streaming].'
        );

        app(AIModelRegistry::class)->get('narrator');
    }

    public function test_invalid_output_format_is_rejected(): void
    {
        config()->set('ai_roles.director.output_format', 'invalid');

        $this->expectException(LogicException::class);

        $this->expectExceptionMessage(
            'AI role [director] has an invalid output format.'
        );

        app(AIModelRegistry::class)->get('director');
    }

    public function test_secret_keys_are_not_part_of_registry_descriptors(): void
    {
        config()->set(
            'ai.providers.ollama.key',
            'fake-private-secret'
        );

        $data = json_encode(
            app(AIModelRegistry::class)->all(),
            JSON_THROW_ON_ERROR
        );

        $this->assertStringNotContainsString(
            'fake-private-secret',
            $data
        );
    }
}
