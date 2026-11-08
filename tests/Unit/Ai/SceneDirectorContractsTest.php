<?php

namespace Tests\Unit\Ai;

use App\Ai\Contracts\SceneDirectorGateway;
use App\Ai\DTOs\AuthorizedSceneContext;
use App\Ai\DTOs\InferenceFailure;
use App\Ai\DTOs\ProviderMetadata;
use App\Ai\DTOs\SceneDirectorRequest;
use App\Ai\DTOs\SceneDirectorResponse;
use App\Ai\DTOs\SceneParticipant;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class SceneDirectorContractsTest extends TestCase
{
    private function request(): SceneDirectorRequest
    {
        return new SceneDirectorRequest(
            sceneId: 11,
            turnId: 20,
            userMessage: 'Mónica, ¿qué opinas?',
            participants: [
                new SceneParticipant(2, 'Mónica', true),
                new SceneParticipant(3, 'Vanesa', false),
            ],
            explicitRecipientProfileIds: [2],
            context: new AuthorizedSceneContext(
                11,
                'director',
                null,
                ['Están en la sala.']
            ),
            maxInterventions: 3,
            remainingInterventions: 3,
        );
    }

    public function test_director_request_serializes_structured_data(): void
    {
        $data = json_decode(
            json_encode($this->request(), JSON_THROW_ON_ERROR),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $this->assertSame(11, $data['scene_id']);
        $this->assertSame([2], $data['explicit_recipient_profile_ids']);
        $this->assertSame('Mónica', $data['participants'][0]['name']);
        $this->assertFalse($data['participants'][1]['present']);
        $this->assertSame('director', $data['context']['audience']);
        $this->assertSame(3, $data['remaining_interventions']);
    }

    public function test_absent_character_can_be_addressed_but_not_selected_here(): void
    {
        $request = $this->request();

        $new = new SceneDirectorRequest(
            $request->sceneId,
            $request->turnId,
            $request->userMessage,
            $request->participants,
            [3],
            $request->context,
            3,
            2,
        );

        $this->assertSame([3], $new->explicitRecipientProfileIds);
    }

    public function test_duplicate_participants_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SceneDirectorRequest(
            11,
            20,
            'Hola',
            [
                new SceneParticipant(2, 'Mónica', true),
                new SceneParticipant(2, 'Copia', true),
            ],
            [2],
            new AuthorizedSceneContext(11, 'director', null, []),
            3,
            3,
        );
    }

    public function test_unknown_explicit_recipient_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SceneDirectorRequest(
            11,
            20,
            'Hola',
            [new SceneParticipant(2, 'Mónica', true)],
            [99],
            new AuthorizedSceneContext(11, 'director', null, []),
            3,
            3,
        );
    }

    public function test_budget_cannot_exceed_maximum(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SceneDirectorRequest(
            11,
            20,
            'Hola',
            [new SceneParticipant(2, 'Mónica', true)],
            [2],
            new AuthorizedSceneContext(11, 'director', null, []),
            3,
            4,
        );
    }

    public function test_director_context_must_match_scene(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SceneDirectorRequest(
            11,
            20,
            'Hola',
            [new SceneParticipant(2, 'Mónica', true)],
            [2],
            new AuthorizedSceneContext(99, 'director', null, []),
            3,
            3,
        );
    }

    public function test_success_response_serializes_metadata_and_decision(): void
    {
        $response = new SceneDirectorResponse(
            11,
            20,
            2,
            'explicit_address',
            true,
            false,
            new ProviderMetadata('simulated'),
        );

        $data = json_decode(
            json_encode($response, JSON_THROW_ON_ERROR),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $this->assertSame(2, $data['next_speaker_profile_id']);
        $this->assertSame('explicit_address', $data['reason']);
        $this->assertTrue($data['metadata']['simulated']);
        $this->assertNull($data['error']);
    }

    public function test_failure_response_contains_only_public_code(): void
    {
        $response = new SceneDirectorResponse(
            11,
            20,
            null,
            null,
            false,
            false,
            new ProviderMetadata('simulated'),
            new InferenceFailure('timeout', true),
        );

        $data = json_decode(
            json_encode($response, JSON_THROW_ON_ERROR),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $this->assertSame('timeout', $data['error']['code']);
        $this->assertTrue($data['error']['retryable']);
        $this->assertNull($data['next_speaker_profile_id']);
    }

    public function test_failure_cannot_contain_speaker_decision(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SceneDirectorResponse(
            11,
            20,
            2,
            'scene_flow',
            true,
            false,
            new ProviderMetadata('simulated'),
            new InferenceFailure('internal_error', false),
        );
    }

    public function test_paused_decision_must_require_user_input(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SceneDirectorResponse(
            11,
            20,
            null,
            'waiting_for_user',
            false,
            false,
            new ProviderMetadata('simulated'),
        );
    }

    public function test_director_rejects_absent_speaker_when_matched_to_request(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new SceneDirectorResponse(
            11,
            20,
            3,
            'scene_flow',
            true,
            false,
            new ProviderMetadata('simulated')
        ))->assertMatches($this->request());
    }

    public function test_director_rejects_exhausted_budget_when_matched_to_request(): void
    {
        $request = $this->request();

        $emptyBudget = new SceneDirectorRequest(
            11,
            20,
            $request->userMessage,
            $request->participants,
            [2],
            $request->context,
            3,
            0,
        );

        $this->expectException(InvalidArgumentException::class);

        (new SceneDirectorResponse(
            11,
            20,
            2,
            'explicit_address',
            true,
            false,
            new ProviderMetadata('simulated')
        ))->assertMatches($emptyBudget);
    }

    public function test_gateway_contract_can_be_implemented_without_ai(): void
    {
        $gateway = new class implements SceneDirectorGateway
        {
            public function decide(
                SceneDirectorRequest $request
            ): SceneDirectorResponse {
                return new SceneDirectorResponse(
                    $request->sceneId,
                    $request->turnId,
                    2,
                    'explicit_address',
                    true,
                    false,
                    new ProviderMetadata('simulated'),
                );
            }
        };

        $response = $gateway->decide($this->request());

        $this->assertSame(
            2,
            $response->nextSpeakerProfileId
        );
    }
}
