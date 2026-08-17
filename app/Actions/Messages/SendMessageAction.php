<?php

namespace App\Actions\Messages;

use App\Ai\Contracts\ChatGateway;
use App\Ai\DTOs\CharacterContext;
use App\Ai\DTOs\ChatContext;
use App\Enums\MessageRole;
use App\Http\Requests\SendMessageRequest;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class SendMessageAction
{
    public function __construct(
        private readonly ChatGateway $chatGateway
    ) {}

    /**
     * @return array{user: Message, assistant: Message}
     */
    public function execute(
        User $user,
        Conversation $conversation,
        string $content
    ): array {
        Gate::forUser($user)->authorize(
            'update',
            $conversation
        );

        $validated = Validator::make(
            [
                'message' => trim($content),
            ],
            SendMessageRequest::messageRules(),
            SendMessageRequest::messageValidationMessages()
        )->validate();

        $context = $this->buildChatContext(
            $conversation,
            $validated['message']
        );

        $reply = $this->chatGateway->generate(
            $context
        );

        return DB::transaction(
            function () use (
                $conversation,
                $validated,
                $reply
            ): array {
                $parentMessageId = $conversation
                    ->messages()
                    ->latest('created_at')
                    ->latest('id')
                    ->value('id');

                $userMessage = $conversation
                    ->messages()
                    ->create([
                        'parent_message_id' => $parentMessageId,
                        'role' => MessageRole::User,
                        'content' => $validated['message'],
                        'metadata' => null,
                        'token_count' => null,
                        'status' => Message::STATUS_COMPLETED,
                    ]);

                $assistantMessage = $conversation
                    ->messages()
                    ->create([
                        'parent_message_id' => $userMessage->id,
                        'role' => MessageRole::Assistant,
                        'content' => $reply->content,
                        'metadata' => $reply->metadata,
                        'token_count' => $reply->tokenCount,
                        'status' => $reply->status,
                    ]);

                $conversation->forceFill([
                    'last_message_at' => $assistantMessage->created_at,
                ])->save();

                $conversation
                    ->userCharacterProfile()
                    ->update([
                        'last_interaction_at' => $assistantMessage->created_at,
                    ]);

                return [
                    'user' => $userMessage,
                    'assistant' => $assistantMessage,
                ];
            }
        );
    }

    private function buildChatContext(
        Conversation $conversation,
        string $newMessage
    ): ChatContext {
        $profile = $conversation
            ->userCharacterProfile()
            ->with('character')
            ->firstOrFail();

        $character = $profile->character;

        $messages = $conversation
            ->messages()
            ->chronological()
            ->get([
                'role',
                'content',
            ])
            ->map(
                fn (Message $message): array => [
                    'role' => $message->role->value,
                    'content' => $message->content,
                ]
            )
            ->values()
            ->all();

        $messages[] = [
            'role' => MessageRole::User->value,
            'content' => $newMessage,
        ];

        $characterContext = new CharacterContext(
            name: $character->name,
            description: $character->description,
            personality: $profile->custom_personality
                ?? $character->base_personality
                ?? [],
            backstory: $character->base_backstory,
            speakingStyle: $profile->custom_speaking_style
                ?? $character->base_speaking_style
                ?? [],
            scenario: $profile->custom_scenario
                ?? $character->base_scenario,
            systemRules: $character->system_rules,
            mood: $profile->current_mood->value,
            relationshipStage: $profile->relationship_stage->value,
            nicknameForUser: $profile->nickname_for_user,
            nicknameForCharacter: $profile->nickname_for_character,
        );

        return new ChatContext(
            conversationId: $conversation->id,
            character: $characterContext,
            messages: $messages,
        );
    }
}
