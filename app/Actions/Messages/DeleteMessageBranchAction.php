<?php

namespace App\Actions\Messages;

use App\Models\Memory;
use App\Models\Message;
use App\Models\RelationshipEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class DeleteMessageBranchAction
{
    public function execute(
        User $user,
        Message $branchRoot
    ): int {
        $conversation =
            $branchRoot
                ->conversation()
                ->firstOrFail();

        Gate::forUser($user)->authorize(
            'update',
            $conversation
        );

        return DB::transaction(
            function () use (
                $conversation,
                $branchRoot
            ): int {
                $root =
                    Message::query()
                        ->whereKey(
                            $branchRoot->id
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                if (
                    $root
                        ->conversation_id
                        !== $conversation->id
                    || $root
                        ->is_active_branch
                ) {
                    throw ValidationException::withMessages([
                        'message_id' =>
                            'Solo se puede eliminar una rama inactiva.',
                    ]);
                }

                $messageIds =
                    $this
                        ->collectBranchIds(
                            $conversation->id,
                            $root->id
                        );

                $containsActiveMessage =
                    Message::query()
                        ->whereIn(
                            'id',
                            $messageIds
                        )
                        ->activeBranch()
                        ->exists();

                if ($containsActiveMessage) {
                    throw ValidationException::withMessages([
                        'message_id' =>
                            'La rama contiene mensajes activos y no puede eliminarse.',
                    ]);
                }

                /*
                 * Never leave a derived memory pointing
                 * to a deleted inactive branch.
                 */
                Memory::query()
                    ->whereIn(
                        'source_message_id',
                        $messageIds
                    )
                    ->delete();

                RelationshipEvent::query()
                    ->where(
                        function ($query) use (
                            $messageIds
                        ): void {
                            $query
                                ->whereIn(
                                    'user_message_id',
                                    $messageIds
                                )
                                ->orWhereIn(
                                    'assistant_message_id',
                                    $messageIds
                                );
                        }
                    )
                    ->delete();

                /*
                 * Delete deepest messages first so
                 * descendants never become accidental
                 * detached roots through nullOnDelete().
                 */
                foreach (
                    array_reverse(
                        $messageIds
                    ) as $messageId
                ) {
                    Message::query()
                        ->whereKey(
                            $messageId
                        )
                        ->delete();
                }

                return count(
                    $messageIds
                );
            }
        );
    }

    /**
     * @return list<int>
     */
    private function collectBranchIds(
        int $conversationId,
        int $rootId
    ): array {
        $all = [];
        $frontier = [
            $rootId,
        ];

        while ($frontier !== []) {
            foreach ($frontier as $id) {
                if (
                    ! in_array(
                        $id,
                        $all,
                        true
                    )
                ) {
                    $all[] = $id;
                }
            }

            $frontier =
                Message::query()
                    ->where(
                        'conversation_id',
                        $conversationId
                    )
                    ->whereIn(
                        'parent_message_id',
                        $frontier
                    )
                    ->pluck(
                        'id'
                    )
                    ->map(
                        static fn (
                            mixed $id
                        ): int =>
                            (int) $id
                    )
                    ->filter(
                        static fn (
                            int $id
                        ): bool =>
                            ! in_array(
                                $id,
                                $all,
                                true
                            )
                    )
                    ->values()
                    ->all();
        }

        return $all;
    }
}
