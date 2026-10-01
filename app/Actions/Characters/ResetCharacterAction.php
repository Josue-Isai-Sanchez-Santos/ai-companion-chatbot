<?php

namespace App\Actions\Characters;

use App\Actions\Conversations\CreateConversationAction;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\ResetAudit;
use App\Models\User;
use App\Models\UserCharacterProfile;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Throwable;

final class ResetCharacterAction
{
    public function __construct(
        private readonly CreateUserCharacterProfileAction $createProfile,
        private readonly CreateConversationAction $createConversation,
        private readonly DeleteCharacterAssets $deleteAssets,
    ) {}

    /**
     * @return array{
     *     profile: UserCharacterProfile,
     *     conversation: Conversation,
     *     audit: ResetAudit,
     *     assets_deleted: bool
     * }
     *
     * @throws AuthorizationException
     */
    public function execute(
        User $user,
        UserCharacterProfile $profile
    ): array {
        if (
            $profile->user_id
            !== $user->id
        ) {
            throw new AuthorizationException(
                'You cannot reset another user\'s character profile.'
            );
        }

        $result = DB::transaction(
            function () use (
                $user,
                $profile
            ): array {
                /*
                 * Lock the profile so two reset requests
                 * cannot mutate it concurrently.
                 */
                $lockedProfile =
                    UserCharacterProfile::query()
                        ->whereKey(
                            $profile->id
                        )
                        ->where(
                            'user_id',
                            $user->id
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $character =
                    $lockedProfile
                        ->character()
                        ->firstOrFail();

                $previousProfileId =
                    $lockedProfile->id;

                $conversationIds =
                    $lockedProfile
                        ->conversations()
                        ->pluck('id');

                $deletedConversations =
                    $conversationIds->count();

                $deletedMessages =
                    $conversationIds
                        ->isEmpty()
                        ? 0
                        : Message::query()
                            ->whereIn(
                                'conversation_id',
                                $conversationIds
                            )
                            ->count();

                $deletedMemories =
                    $lockedProfile
                        ->memories()
                        ->count();

                $deletedRelationshipEvents =
                    $lockedProfile
                        ->relationshipEvents()
                        ->count();

                /*
                 * This one delete is the destructive
                 * boundary. Database cascades remove:
                 *
                 * - conversations;
                 * - messages and branches;
                 * - summaries;
                 * - memories and embeddings;
                 * - relationship events.
                 *
                 * Profile-local personality, scenario,
                 * relationship and emotional state
                 * disappear with the profile row itself.
                 */
                $lockedProfile->delete();

                /*
                 * Rebuild from the immutable/base
                 * character configuration.
                 */
                $newProfile =
                    $this
                        ->createProfile
                        ->execute(
                            $user,
                            $character
                        );

                $newConversation =
                    $this
                        ->createConversation
                        ->execute(
                            $user,
                            $newProfile
                        );

                $audit =
                    ResetAudit::query()
                        ->create([
                            'user_id' =>
                                $user->id,

                            'character_id' =>
                                $character->id,

                            'previous_profile_id' =>
                                $previousProfileId,

                            'new_profile_id' =>
                                $newProfile->id,

                            'deleted_conversations' =>
                                $deletedConversations,

                            'deleted_messages' =>
                                $deletedMessages,

                            'deleted_memories' =>
                                $deletedMemories,

                            'deleted_relationship_events' =>
                                $deletedRelationshipEvents,

                            'reset_at' =>
                                now(),
                        ]);

                return [
                    'profile' =>
                        $newProfile,

                    'conversation' =>
                        $newConversation,

                    'audit' =>
                        $audit,

                    'previous_profile_id' =>
                        $previousProfileId,
                ];
            }
        );

        /*
         * Filesystem deletion MUST occur only after
         * the database transaction has committed.
         *
         * A storage failure cannot roll back an
         * already committed database transaction, so
         * it is reported separately.
         */
        $assetsDeleted = true;

        try {
            $this
                ->deleteAssets
                ->execute(
                    $result[
                        'previous_profile_id'
                    ]
                );
        } catch (Throwable $exception) {
            $assetsDeleted = false;

            report(
                $exception
            );
        }

        return [
            'profile' =>
                $result['profile'],

            'conversation' =>
                $result['conversation'],

            'audit' =>
                $result['audit'],

            'assets_deleted' =>
                $assetsDeleted,
        ];
    }
}
