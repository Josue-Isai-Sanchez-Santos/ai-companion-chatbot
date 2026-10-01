<?php

namespace App\Jobs;

use App\Ai\Agents\ConversationSummaryAgent;
use App\Ai\Prompts\SummaryPromptBuilder;
use App\Enums\MessageRole;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Responses\StructuredAgentResponse;
use LogicException;

final class RefreshConversationSummary implements
    ShouldQueue,
    ShouldBeUnique
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 240;

    public int $uniqueFor = 3600;

    public function __construct(
        public readonly int $conversationId,
        public readonly int $throughMessageId,
    ) {
        $this->onQueue(
            (string) config(
                'chatbot.summary.queue',
                'summary'
            )
        );

        $this->afterCommit();
    }

    /**
     * Only one summary job for the same conversation
     * should execute concurrently.
     */
    public function uniqueId(): string
    {
        return sprintf(
            '%d:%d',
            $this->conversationId,
            $this->throughMessageId
        );
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [
            10,
            30,
            60,
        ];
    }

    public function handle(
        ConversationSummaryAgent $agent,
        SummaryPromptBuilder $promptBuilder
    ): void {
        if (
            ! (bool) config(
                'chatbot.summary.enabled',
                true
            )
        ) {
            return;
        }

        $conversation =
            Conversation::query()
                ->find(
                    $this->conversationId
                );

        if ($conversation === null) {
            return;
        }

        $anchor = $conversation
            ->messages()
            ->activeBranch()
            ->whereKey(
                $this->throughMessageId
            )
            ->where(
                'role',
                MessageRole::Assistant->value
            )
            ->where(
                'status',
                Message::STATUS_COMPLETED
            )
            ->first();

        if ($anchor === null) {
            return;
        }

        $threshold = max(
            2,
            (int) config(
                'chatbot.summary.message_threshold',
                12
            )
        );

        $query = $conversation
            ->messages()
            ->activeBranch()
            ->where(
                'id',
                '<=',
                $this->throughMessageId
            )
            ->where(
                'status',
                Message::STATUS_COMPLETED
            )
            ->whereIn(
                'role',
                [
                    MessageRole::User->value,
                    MessageRole::Assistant->value,
                ]
            );

        /*
         * >= is intentional.
         *
         * summary_updated_at points to the last
         * summarized message time. Re-reading the
         * boundary message is safer than accidentally
         * skipping a message created in the same
         * timestamp precision.
         *
         * SummaryPromptBuilder tells the model to
         * remove this overlap.
         */
        if (
            $conversation->summary_updated_at
            !== null
        ) {
            $query->where(
                'created_at',
                '>=',
                $conversation
                    ->summary_updated_at
            );
        }

        if (
            (clone $query)->count()
            < $threshold
        ) {
            return;
        }

        $maximumMessages = max(
            $threshold,
            (int) config(
                'chatbot.summary.max_messages_per_refresh',
                40
            )
        );

        /*
         * Process the oldest pending block first.
         * If a very large backlog exists, later jobs
         * can continue from this checkpoint.
         */
        $messages = $query
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit(
                $maximumMessages
            )
            ->get();

        if (
            $messages->count()
            < $threshold
        ) {
            return;
        }

        $payload = $messages
            ->map(
                static fn (
                    Message $message
                ): array => [
                    'id' =>
                        $message->id,

                    'role' =>
                        $message->role->value,

                    'content' =>
                        $message->content,
                ]
            )
            ->values()
            ->all();

        $maxCharacters = max(
            500,
            (int) config(
                'chatbot.summary.max_characters',
                2500
            )
        );

        $response = $agent
            ->prompt(
                $promptBuilder->build(
                    $conversation->summary,
                    $payload,
                    $maxCharacters
                ),

                provider:
                    $this->provider(),

                model:
                    $this->model(),

                timeout:
                    max(
                        1,
                        (int) config(
                            'chatbot.summary.timeout',
                            120
                        )
                    )
            );

        if (
            ! $response
                instanceof StructuredAgentResponse
        ) {
            throw new LogicException(
                'Conversation summary agent did not return structured output.'
            );
        }

        $summary = trim(
            (string) (
                $response['summary']
                ?? ''
            )
        );

        if ($summary === '') {
            throw new LogicException(
                'Conversation summary agent returned an empty summary.'
            );
        }

        $summary = mb_substr(
            $summary,
            0,
            $maxCharacters
        );

        $coveredMessageIds =
            $messages
                ->pluck('id')
                ->map(
                    static fn (
                        mixed $id
                    ): int =>
                        (int) $id
                )
                ->all();

        $coveredThrough =
            $messages->last();

        if (
            ! $coveredThrough
                instanceof Message
        ) {
            return;
        }

        DB::transaction(
            function () use (
                $summary,
                $coveredThrough,
                $coveredMessageIds
            ): void {
                $conversation =
                    Conversation::query()
                        ->whereKey(
                            $this->conversationId
                        )
                        ->lockForUpdate()
                        ->first();

                if ($conversation === null) {
                    return;
                }

                /*
                 * A regeneration may have changed
                 * branches while the summarizer was
                 * talking to the AI provider.
                 */
                $stillActive =
                    Message::query()
                        ->where(
                            'conversation_id',
                            $this->conversationId
                        )
                        ->activeBranch()
                        ->whereIn(
                            'id',
                            $coveredMessageIds
                        )
                        ->count();

                if (
                    $stillActive
                    !== count(
                        $coveredMessageIds
                    )
                ) {
                    return;
                }

                $conversation->forceFill([
                    'summary' =>
                        $summary,

                    /*
                     * This is the timestamp of the
                     * latest message covered by this
                     * summary, not simply now().
                     */
                    'summary_updated_at' =>
                        $coveredThrough
                            ->created_at,
                ])->save();
            }
        );
    }

    private function provider(): string
    {
        $provider = trim(
            (string) config(
                'chatbot.summary.provider'
            )
        );

        if ($provider !== '') {
            return $provider;
        }

        return (string) config(
            'ai.chat.provider'
        );
    }

    private function model(): string
    {
        $model = trim(
            (string) config(
                'chatbot.summary.model'
            )
        );

        if ($model !== '') {
            return $model;
        }

        return (string) config(
            'ai.chat.model'
        );
    }
}
