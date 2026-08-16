<?php

namespace Tests\Feature\Configuration;

use Tests\TestCase;

class DomainConfigurationTest extends TestCase
{
    public function test_chatbot_configuration_has_expected_defaults(): void
    {
        $this->assertSame(
            20,
            config('chatbot.recent_message_limit')
        );

        $this->assertSame(
            'neutral',
            config('chatbot.default_mood')
        );

        $this->assertSame(
            'BORRAR',
            config('chatbot.reset_confirmation')
        );

        $this->assertTrue(
            config('chatbot.streaming')
        );
    }

    public function test_memory_configuration_has_valid_defaults(): void
    {
        $this->assertTrue(config('memory.enabled'));

        $this->assertSame(
            8,
            config('memory.retrieval_limit')
        );

        $this->assertSame(
            0.65,
            config('memory.minimum_similarity')
        );

        $this->assertSame(
            0.40,
            config('memory.minimum_importance')
        );
    }

    public function test_relationship_metrics_have_valid_ranges(): void
    {
        $this->assertSame(
            'strangers',
            config('relationship.default_stage')
        );

        foreach ([
            'trust',
            'affection',
            'familiarity',
            'tension',
        ] as $metric) {
            $configuration = config(
                "relationship.metrics.{$metric}"
            );

            $this->assertSame(0, $configuration['min']);
            $this->assertSame(100, $configuration['max']);
            $this->assertSame(0, $configuration['default']);

            $this->assertLessThan(
                $configuration['max'],
                $configuration['min']
            );
        }
    }
}
