<?php

namespace Tests\Unit\Ai;

use App\Ai\Contracts\NarrativeGateway;
use App\Ai\DTOs\AuthorizedSceneContext;
use App\Ai\DTOs\InferenceFailure;
use App\Ai\DTOs\NarrativeRequest;
use App\Ai\DTOs\NarrativeResponse;
use App\Ai\DTOs\NarrativeSegment;
use App\Ai\DTOs\ProviderMetadata;
use App\Ai\DTOs\SceneDirectorRequest;
use App\Ai\DTOs\SceneDirectorResponse;
use App\Ai\DTOs\SceneParticipant;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class NarrativeContractsTest extends TestCase
{
    private function request(): NarrativeRequest
    {
        return new NarrativeRequest(
            sceneId: 11,
            turnId: 20,
            speakerProfileId: 2,
            userMessage: 'Mónica, ¿qué opinas?',
            participants: [
                new SceneParticipant(2, 'Mónica', true),
                new SceneParticipant(3, 'Vanesa', true),
            ],
            recipientProfileIds: [3],
            context: new AuthorizedSceneContext(
                11,
                'participant',
                2,
                ['Mónica conoce el secreto.']
            ),
            remainingInterventions: 2,
        );
    }

    public function test_narrative_request_serializes_scoped_context(): void
    {
        $data = json_decode(
            json_encode($this->request(), JSON_THROW_ON_ERROR),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $this->assertSame(2, $data['speaker_profile_id']);
        $this->assertSame([3], $data['recipient_profile_ids']);
        $this->assertSame(2, $data['context']['viewer_profile_id']);
        $this->assertSame(
            ['Mónica conoce el secreto.'],
            $data['context']['facts']
        );
    }

    public function test_narrative_context_must_belong_to_speaker(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new NarrativeRequest(
            11,
            20,
            2,
            'Hola',
            [new SceneParticipant(2, 'Mónica', true)],
            [],
            new AuthorizedSceneContext(
                11,
                'participant',
                3,
                ['Secreto ajeno']
            ),
            2,
        );
    }

    public function test_absent_speaker_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new NarrativeRequest(
            11,
            20,
            2,
            'Hola',
            [new SceneParticipant(2, 'Mónica', false)],
            [],
            new AuthorizedSceneContext(
                11,
                'participant',
                2,
                []
            ),
            2,
        );
    }

    public function test_invalid_recipient_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new NarrativeRequest(
            11,
            20,
            2,
            'Hola',
            [new SceneParticipant(2, 'Mónica', true)],
            [5],
            new AuthorizedSceneContext(
                11,
                'participant',
                2,
                []
            ),
            2,
        );
    }

    public function test_exhausted_budget_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new NarrativeRequest(
            11,
            20,
            2,
            'Hola',
            [new SceneParticipant(2, 'Mónica', true)],
            [],
            new AuthorizedSceneContext(
                11,
                'participant',
                2,
                []
            ),
            0,
        );
    }

    public function test_valid_three_segment_response_serializes(): void
    {
        $response = new NarrativeResponse(
            11,
            20,
            2,
            [
                new NarrativeSegment(
                    'thought',
                    'Debo decidir.'
                ),
                new NarrativeSegment(
                    'action',
                    'Mónica abre la puerta.'
                ),
                new NarrativeSegment(
                    'dialogue',
                    'Adelante.'
                ),
            ],
            new ProviderMetadata('simulated'),
        );

        $data = json_decode(
            json_encode($response, JSON_THROW_ON_ERROR),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $this->assertSame(
            ['thought', 'action', 'dialogue'],
            array_column($data['segments'], 'type')
        );
        $this->assertSame(
            'Adelante.',
            $data['segments'][2]['text']
        );
        $this->assertTrue($data['metadata']['simulated']);
        $this->assertNull($data['error']);
    }

    public function test_narrative_response_rejects_different_speaker(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new NarrativeResponse(
            11,
            20,
            3,
            [new NarrativeSegment('dialogue', 'Hola')],
            new ProviderMetadata('simulated'),
        ))->assertMatches($this->request());
    }

    public function test_unknown_segment_type_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new NarrativeSegment(
            'system',
            'Instrucciones'
        );
    }

    public function test_empty_segment_text_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new NarrativeSegment(
            'dialogue',
            '  '
        );
    }

    public function test_success_response_requires_segments(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new NarrativeResponse(
            11,
            20,
            2,
            [],
            new ProviderMetadata('simulated'),
        );
    }

    public function test_failed_response_cannot_contain_segments(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new NarrativeResponse(
            11,
            20,
            2,
            [new NarrativeSegment('dialogue', 'Hola')],
            new ProviderMetadata('simulated'),
            new InferenceFailure('timeout', true),
        );
    }

    public function test_failure_is_serialized_without_raw_exception(): void
    {
        $response = new NarrativeResponse(
            11,
            20,
            2,
            [],
            new ProviderMetadata('simulated'),
            new InferenceFailure('unavailable', true),
        );

        $data = json_decode(
            json_encode($response, JSON_THROW_ON_ERROR),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $this->assertSame('unavailable', $data['error']['code']);
        $this->assertSame([], $data['segments']);
    }

    public function test_invalid_provider_metadata_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ProviderMetadata(
            'simulated',
            'ollama',
            'secret'
        );
    }

    public function test_provider_metadata_does_not_include_credentials(): void
    {
        $metadata = new ProviderMetadata(
            'laravel',
            'ollama',
            'qwen3:4b-instruct',
            20,
            10,
            100
        );

        $data = json_decode(
            json_encode($metadata, JSON_THROW_ON_ERROR),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $this->assertSame(
            'qwen3:4b-instruct',
            $data['model']
        );
        $this->assertArrayNotHasKey('api_key', $data);
        $this->assertArrayNotHasKey('token', $data);
    }

    public function test_invalid_failure_code_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new InferenceFailure(
            'raw-exception-with-secret',
            true
        );
    }

    public function test_contracts_are_readonly(): void
    {
        foreach ([
            SceneParticipant::class,
            AuthorizedSceneContext::class,
            SceneDirectorRequest::class,
            SceneDirectorResponse::class,
            NarrativeRequest::class,
            NarrativeResponse::class,
            NarrativeSegment::class,
            ProviderMetadata::class,
            InferenceFailure::class,
        ] as $type) {
            $this->assertTrue(
                (new \ReflectionClass($type))->isReadOnly()
            );
        }
    }

    public function test_gateway_contract_supports_generation_and_segment_streaming(): void
    {
        $gateway = new class implements NarrativeGateway
        {
            public function generate(
                NarrativeRequest $request
            ): NarrativeResponse {
                return new NarrativeResponse(
                    $request->sceneId,
                    $request->turnId,
                    $request->speakerProfileId,
                    [
                        new NarrativeSegment(
                            'dialogue',
                            'Respuesta simulada.'
                        ),
                    ],
                    new ProviderMetadata('simulated')
                );
            }

            public function stream(
                NarrativeRequest $request,
                callable $onSegment
            ): NarrativeResponse {
                $response = $this->generate($request);

                foreach ($response->segments as $segment) {
                    $onSegment($segment);
                }

                return $response;
            }
        };

        $received = [];

        $response = $gateway->stream(
            $this->request(),
            function (NarrativeSegment $segment) use (&$received): void {
                $received[] = $segment->text;
            }
        );

        $this->assertSame(
            ['Respuesta simulada.'],
            $received
        );

        $this->assertSame(
            'simulated',
            $response->metadata->driver
        );
    }
}
