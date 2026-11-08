<?php

namespace Tests\Feature\Ai;

use App\Ai\Contracts\AIRoleRouterContract;
use App\Ai\DTOs\AIModelDescriptor;
use App\Ai\Routing\AIModelRegistry;
use App\Ai\Routing\AIRoleRouter;
use Illuminate\Support\Facades\Http;
use LogicException;
use Tests\TestCase;

final class AIRoleRouterTest extends TestCase
{
    public function test_container_resolves_router_contract(): void
    {
        $router = app(AIRoleRouterContract::class);

        $this->assertInstanceOf(
            AIRoleRouter::class,
            $router
        );
    }

    public function test_disabled_v2_preserves_legacy_routing(): void
    {
        config()->set('ai_roles.enabled', false);

        $router = app(AIRoleRouterContract::class);

        $chat = $router->forRole('legacy_chat');
        $embedding = $router->forRole('embedding');

        $this->assertSame(
            'legacy_chat',
            $chat->role
        );

        $this->assertSame(
            'embedding',
            $embedding->role
        );
    }

    public function test_disabled_v2_rejects_director(): void
    {
        config()->set('ai_roles.enabled', false);

        $this->expectException(
            LogicException::class
        );

        $this->expectExceptionMessage(
            'AI v2 is disabled. Role [director] cannot be routed.'
        );

        app(AIRoleRouterContract::class)
            ->forRole('director');
    }

    public function test_disabled_v2_rejects_narrator(): void
    {
        config()->set('ai_roles.enabled', false);

        $this->expectException(
            LogicException::class
        );

        $this->expectExceptionMessage(
            'AI v2 is disabled. Role [narrator] cannot be routed.'
        );

        app(AIRoleRouterContract::class)
            ->forRole('narrator');
    }

    public function test_enabled_v2_resolves_simulated_director(): void
    {
        config()->set('ai_roles.enabled', true);

        $director = app(AIRoleRouterContract::class)
            ->forRole('director');

        $this->assertSame('director', $director->role);

        $this->assertSame(
            AIModelDescriptor::SIMULATED,
            $director->status
        );

        $this->assertSame('json', $director->outputFormat);
    }

    public function test_enabled_v2_resolves_simulated_narrator(): void
    {
        config()->set('ai_roles.enabled', true);

        $narrator = app(AIRoleRouterContract::class)
            ->forRole('narrator');

        $this->assertSame('narrator', $narrator->role);

        $this->assertSame(
            AIModelDescriptor::SIMULATED,
            $narrator->status
        );

        $this->assertTrue(
            $narrator->supportsStreaming
        );
    }

    public function test_tasks_resolve_to_expected_roles(): void
    {
        config()->set('ai_roles.enabled', true);

        $router = app(AIRoleRouterContract::class);

        $expected = [
            'scene_direction' => 'director',
            'narrative_generation' => 'narrator',
            'memory_embedding' => 'embedding',
            'legacy_message' => 'legacy_chat',
        ];

        foreach ($expected as $task => $role) {
            $descriptor = $router->forTask($task);

            $this->assertSame(
                $role,
                $descriptor->role
            );
        }
    }

    public function test_unknown_role_is_rejected(): void
    {
        $this->expectException(
            LogicException::class
        );

        $this->expectExceptionMessage(
            'Unknown AI role [unknown].'
        );

        app(AIRoleRouterContract::class)
            ->forRole('unknown');
    }

    public function test_unknown_task_is_rejected(): void
    {
        $this->expectException(
            LogicException::class
        );

        $this->expectExceptionMessage(
            'Unknown AI task [unknown_task].'
        );

        app(AIRoleRouterContract::class)
            ->forTask('unknown_task');
    }

    public function test_unconfigured_legacy_role_has_clear_error(): void
    {
        config()->set('ai_roles.enabled', false);
        config()->set('ai.chat.driver', null);

        $this->expectException(
            LogicException::class
        );

        $this->expectExceptionMessage(
            'AI role [legacy_chat] is unconfigured.'
        );

        app(AIRoleRouterContract::class)
            ->forRole('legacy_chat');
    }

    public function test_router_does_not_choose_fixed_model(): void
    {
        config()->set('ai_roles.enabled', false);

        config()->set('ai.chat.driver', 'laravel');
        config()->set('ai.chat.provider', 'test-provider');
        config()->set('ai.chat.model', 'model-from-configuration');

        $chat = app(AIRoleRouterContract::class)
            ->forTask('legacy_message');

        $this->assertSame(
            'test-provider',
            $chat->provider
        );

        $this->assertSame(
            'model-from-configuration',
            $chat->model
        );
    }

    public function test_invalid_v2_configuration_is_rejected(): void
    {
        config()->set('ai_roles.enabled', true);
        config()->set('ai_roles.director.driver', 'laravel');

        $this->expectException(
            LogicException::class
        );

        $this->expectExceptionMessage(
            'AI role [director] must use the simulated driver.'
        );

        app(AIRoleRouterContract::class)
            ->forRole('director');
    }

    public function test_routing_does_not_send_http_requests(): void
    {
        Http::fake();

        config()->set('ai_roles.enabled', true);

        $router = app(AIRoleRouterContract::class);

        $router->forRole('director');
        $router->forRole('narrator');
        $router->forRole('embedding');
        $router->forRole('legacy_chat');

        Http::assertNothingSent();
    }

    public function test_narrative_consumer_accepts_injected_router(): void
    {
        $expected = app(AIModelRegistry::class)
            ->get('narrator');

        /*
         * Replace the router contract with a
         * PHPUnit mock through Laravel's container.
         */
        $fake = $this->createMock(
            AIRoleRouterContract::class
        );

        $fake->expects($this->once())
            ->method('forTask')
            ->with('narrative_generation')
            ->willReturn($expected);

        $this->app->instance(
            AIRoleRouterContract::class,
            $fake
        );

        $consumer = $this->app->make(
            NarrativeConsumerProbe::class
        );

        $this->assertSame(
            $expected,
            $consumer->selectNarrator()
        );
    }
}

/**
 * Test-only example of a future narrative service.
 *
 * It depends on the routing contract, not
 * a particular model or AI provider.
 */
final class NarrativeConsumerProbe
{
    public function __construct(
        private readonly AIRoleRouterContract $router,
    ) {}

    public function selectNarrator(): AIModelDescriptor
    {
        return $this->router->forTask(
            'narrative_generation'
        );
    }
}
