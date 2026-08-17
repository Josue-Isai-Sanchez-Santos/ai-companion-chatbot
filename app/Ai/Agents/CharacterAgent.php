<?php

namespace App\Ai\Agents;

use App\Ai\Contracts\ChatGateway;
use App\Ai\DTOs\CharacterContext;
use App\Ai\DTOs\ChatContext;
use App\Ai\DTOs\GeneratedReply;
use App\Ai\Prompts\CharacterPromptBuilder;
use App\Enums\MessageRole;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

final class CharacterAgent
{
    public function __construct(
        private readonly ChatGateway $chatGateway,
        private readonly CharacterPromptBuilder $promptBuilder,
    ) {}

    /**
     * @param  list<string>  $relevantMemories
     */
    public function reply(
        User $user,
        Conversation $conversation,
        string $newMessage,
        array $relevantMemories = []
    ): GeneratedReply {
        $context = $this->contextFor(
            $user,
            $conversation,
            $newMessage,
            $relevantMemories
        );

        return $this->chatGateway->generate(
            $context
        );
    }

    /**
     * @param  list<string>  $relevantMemories
     */
    public function contextFor(
        User $user,
        Conversation $conversation,
        string $newMessage,
        array $relevantMemories = []
    ): ChatContext {
        Gate::forUser($user)->authorize(
            'view',
            $conversation
        );

        $profile = $conversation
            ->userCharacterProfile()
            ->with('character')
            ->firstOrFail();

        $character = $profile->character;

        $messageLimit = max(
            1,
            (int) config(
                'chatbot.recent_message_limit',
                20
            )
        );

        $historyLimit = max(
            0,
            $messageLimit - 1
        );

        $messages = [];

        if ($historyLimit > 0) {
            $messages = $conversation
                ->messages()
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit($historyLimit)
                ->get([
                    'id',
                    'role',
                    'content',
                    'created_at',
                ])
                ->reverse()
                ->values()
                ->map(
                    fn (Message $message): array => [
                        'role' => $message->role->value,
                        'content' => $message->content,
                    ]
                )
                ->all();
        }

        $messages[] = [
            'role' => MessageRole::User->value,
            'content' => $newMessage,
        ];

        $summary = $this->optionalText(
            $conversation->summary
        );

        $relevantMemories = $this
            ->normalizeMemories(
                $relevantMemories
            );

        $characterContext = new CharacterContext(
            name: $character->name,
            description: $character->description,

            personality: $character->base_personality
                ?? [],

            backstory: $character->base_backstory,

            speakingStyle: $character->base_speaking_style
                ?? [],

            scenario: $character->base_scenario,

            systemRules: $character->system_rules,

            mood: $profile->current_mood->value,

            relationshipStage: $profile->relationship_stage->value,

            nicknameForUser: $profile->nickname_for_user,

            nicknameForCharacter: $profile->nickname_for_character,

            customPersonality: $profile->custom_personality
                ?? [],

            customSpeakingStyle: $profile->custom_speaking_style
                ?? [],

            customScenario: $profile->custom_scenario,

            trust: $profile->trust,

            affection: $profile->affection,

            familiarity: $profile->familiarity,

            tension: $profile->tension,
        );

        $systemPrompt = $this
            ->promptBuilder
            ->build(
                $characterContext,
                $summary,
                $relevantMemories
            );

        return new ChatContext(
            conversationId: $conversation->id,
            character: $characterContext,
            messages: $messages,
            systemPrompt: $systemPrompt,
            conversationSummary: $summary,
            relevantMemories: $relevantMemories,
        );
    }

    private function optionalText(
        ?string $value
    ): ?string {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === ''
            ? null
            : $value;
    }

    /**
     * @param  list<string>  $memories
     * @return list<string>
     */
    private function normalizeMemories(
        array $memories
    ): array {
        $normalized = [];

        foreach ($memories as $memory) {
            $memory = trim($memory);

            if ($memory !== '') {
                $normalized[] = $memory;
            }
        }

        return $normalized;
    }
}
