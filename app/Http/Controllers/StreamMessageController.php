<?php

namespace App\Http\Controllers;

use App\Actions\Messages\StreamMessageAction;
use App\Ai\Agents\CharacterAgent;
use App\Ai\Exceptions\AiGatewayException;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class StreamMessageController extends Controller
{
    public function stream(
        Request $request,
        StreamMessageAction $streamMessage,
        CharacterAgent $characterAgent
    ): StreamedResponse {
        $validated = $request->validate([
            'conversation_id' => [
                'required',
                'integer',
            ],
        ]);

        $conversation = Conversation::query()
            ->findOrFail(
                $validated['conversation_id']
            );

        $user = $request->user();

        abort_unless(
            $user instanceof User,
            401
        );

        $state = $streamMessage->start(
            $user,
            $conversation,
            (string) $request->input(
                'message'
            )
        );

        return $this->streamResponse(
            $user,
            $state,
            $streamMessage,
            $characterAgent
        );
    }

    public function retry(
        Request $request,
        StreamMessageAction $streamMessage,
        CharacterAgent $characterAgent
    ): StreamedResponse {
        $validated = $request->validate([
            'assistant_message_id' => [
                'required',
                'integer',
            ],
        ]);

        $assistant = Message::query()
            ->findOrFail(
                $validated['assistant_message_id']
            );

        $user = $request->user();

        abort_unless(
            $user instanceof User,
            401
        );

        $state = $streamMessage->retry(
            $user,
            $assistant
        );

        return $this->streamResponse(
            $user,
            $state,
            $streamMessage,
            $characterAgent
        );
    }

    /**
     * @param  array{
     *     conversation: Conversation,
     *     user: Message,
     *     assistant: Message
     * }  $state
     */
    private function streamResponse(
        User $user,
        array $state,
        StreamMessageAction $streamMessage,
        CharacterAgent $characterAgent
    ): StreamedResponse {
        $conversation = $state['conversation'];
        $userMessage = $state['user'];
        $assistant = $state['assistant'];

        return response()->stream(
            function () use (
                $user,
                $conversation,
                $userMessage,
                $assistant,
                $streamMessage,
                $characterAgent
            ): void {
                ignore_user_abort(true);

                $partialContent = '';
                $clientDisconnected = false;

                try {
                    $this->emit(
                        'started',
                        [
                            'conversation_id' =>
                                $conversation->id,

                            'user_message_id' =>
                                $userMessage->id,

                            'assistant_message_id' =>
                                $assistant->id,

                            'attempt' => (int) data_get(
                                $assistant->metadata,
                                'stream.attempt',
                                1
                            ),
                        ]
                    );

                    $reply = $characterAgent->streamReply(
                        $user,
                        $conversation,
                        $userMessage->content,

                        function (
                            string $delta
                        ) use (
                            &$partialContent,
                            &$clientDisconnected,
                            $assistant,
                            $streamMessage
                        ): void {
                            $partialContent .= $delta;

                            $this->emit(
                                'delta',
                                [
                                    'assistant_message_id' =>
                                        $assistant->id,

                                    'delta' => $delta,
                                ]
                            );

                            if (connection_aborted()) {
                                $clientDisconnected = true;

                                $streamMessage->interrupt(
                                    $assistant,
                                    $partialContent
                                );

                                throw new \RuntimeException(
                                    'Client disconnected during AI stream.'
                                );
                            }
                        },

                        persistedMessage: $userMessage
                    );

                    if (connection_aborted()) {
                        $streamMessage->interrupt(
                            $assistant,
                            $partialContent
                        );

                        return;
                    }

                    $completed = $streamMessage->complete(
                        $assistant,
                        $reply
                    );

                    $this->emit(
                        'completed',
                        [
                            'assistant_message_id' =>
                                $completed->id,

                            'status' =>
                                $completed->status,

                            'token_count' =>
                                $completed->token_count,
                        ]
                    );
                } catch (AiGatewayException $exception) {
                    if ($clientDisconnected) {
                        return;
                    }

                    report($exception);

                    $failed = $streamMessage->fail(
                        $assistant,
                        $partialContent,
                        $exception
                    );

                    $this->emit(
                        'failed',
                        [
                            'assistant_message_id' =>
                                $failed->id,

                            'status' =>
                                $failed->status,

                            'message' =>
                                'No fue posible completar la respuesta de IA. Puedes reintentarla.',
                        ]
                    );
                } catch (Throwable $exception) {
                    if ($clientDisconnected) {
                        return;
                    }

                    report($exception);

                    $failed = $streamMessage->fail(
                        $assistant,
                        $partialContent,
                        $exception
                    );

                    $this->emit(
                        'failed',
                        [
                            'assistant_message_id' =>
                                $failed->id,

                            'status' =>
                                $failed->status,

                            'message' =>
                                'La respuesta se interrumpió por un error inesperado. Puedes reintentarla.',
                        ]
                    );
                }
            },

            200,

            [
                'Content-Type' => 'text/event-stream',

                'Cache-Control' =>
                    'no-cache, no-transform',

                'X-Accel-Buffering' => 'no',
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function emit(
        string $event,
        array $data
    ): void {
        $json = json_encode(
            $data,
            JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_INVALID_UTF8_SUBSTITUTE
        );

        echo "event: {$event}\n";
        echo 'data: '.$json."\n\n";

        if (ob_get_level() > 0) {
            @ob_flush();
        }

        flush();
    }
}
