<?php

namespace App\Livewire\Chat;

use App\Actions\Characters\CreateUserCharacterProfileAction;
use App\Actions\Conversations\CreateConversationAction;
use App\Actions\Conversations\DeleteConversationAction;
use App\Actions\Conversations\RenameConversationAction;
use App\Actions\Messages\SendMessageAction;
use App\Enums\CharacterMood;
use App\Enums\RelationshipStage;
use App\Models\Character;
use App\Models\Conversation;
use App\Models\User;
use App\Models\UserCharacterProfile;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Title('Chat')]
class ChatPage extends Component
{
    #[Locked]
    public int $profileId;

    #[Locked]
    public ?int $conversationId = null;

    #[Locked]
    public ?int $renamingConversationId = null;

    #[Validate('required|string|max:160')]
    public string $renamingTitle = '';

    public string $message = '';

    public function mount(
        CreateUserCharacterProfileAction $createProfile
    ): void {
        /** @var User $user */
        $user = auth()->user();

        $character = Character::query()
            ->active()
            ->sole();

        $profile = $createProfile->execute(
            $user,
            $character
        );

        $this->profileId = $profile->id;

        $this->conversationId = $profile
            ->conversations()
            ->latest('updated_at')
            ->value('id');
    }

    public function createConversation(
        CreateConversationAction $createConversation
    ): void {
        /** @var User $user */
        $user = auth()->user();

        $conversation = $createConversation->execute(
            $user,
            $this->currentProfile()
        );

        $this->conversationId = $conversation->id;

        $this->cancelRenamingConversation();
    }

    public function selectConversation(
        int $conversationId
    ): void {
        $conversation = $this->conversationForCurrentProfile(
            $conversationId,
            'view'
        );

        $this->conversationId = $conversation->id;

        $this->cancelRenamingConversation();
    }

    public function startRenamingConversation(
        int $conversationId
    ): void {
        $conversation = $this->conversationForCurrentProfile(
            $conversationId,
            'update'
        );

        $this->renamingConversationId = $conversation->id;
        $this->renamingTitle = $conversation->title;

        $this->resetValidation('renamingTitle');
    }

    public function renameConversation(
        RenameConversationAction $renameConversation
    ): void {
        if ($this->renamingConversationId === null) {
            return;
        }

        $this->validateOnly('renamingTitle');

        /** @var User $user */
        $user = auth()->user();

        $conversation = $this->conversationForCurrentProfile(
            $this->renamingConversationId,
            'update'
        );

        $renameConversation->execute(
            $user,
            $conversation,
            $this->renamingTitle
        );

        $this->cancelRenamingConversation();
    }

    public function cancelRenamingConversation(): void
    {
        $this->renamingConversationId = null;
        $this->renamingTitle = '';

        $this->resetValidation('renamingTitle');
    }

    public function deleteConversation(
        int $conversationId,
        DeleteConversationAction $deleteConversation
    ): void {
        /** @var User $user */
        $user = auth()->user();

        $conversation = $this->conversationForCurrentProfile(
            $conversationId,
            'delete'
        );

        $wasSelected = $this->conversationId === $conversation->id;

        $deleteConversation->execute(
            $user,
            $conversation
        );

        if ($this->renamingConversationId === $conversationId) {
            $this->cancelRenamingConversation();
        }

        if ($wasSelected) {
            $this->conversationId = $this
                ->currentProfile()
                ->conversations()
                ->latest('updated_at')
                ->value('id');
        }
    }

    public function sendMessage(
        SendMessageAction $sendMessage
    ): void {
        if ($this->conversationId === null) {
            $this->addError(
                'message',
                'Selecciona una conversación antes de enviar.'
            );

            return;
        }

        /** @var User $user */
        $user = auth()->user();

        $conversation = $this->conversationForCurrentProfile(
            $this->conversationId,
            'update'
        );

        $sendMessage->execute(
            $user,
            $conversation,
            $this->message
        );

        $this->message = '';

        $this->resetValidation('message');

        $this->dispatch(
            'messages-updated',
            conversationId: $conversation->id
        );
    }

    public function render(): View
    {
        $profile = $this->currentProfile();

        Gate::authorize(
            'viewAny',
            [
                Conversation::class,
                $profile,
            ]
        );

        $conversations = $profile
            ->conversations()
            ->latest('updated_at')
            ->get();

        $selectedConversation = $this->conversationId === null
            ? null
            : $conversations->firstWhere(
                'id',
                $this->conversationId
            );

        return view('livewire.chat.chat-page', [
            'profile' => $profile,
            'character' => $profile->character,
            'conversations' => $conversations,
            'selectedConversation' => $selectedConversation,

            'moodLabel' => $this->moodLabel(
                $profile->current_mood
            ),

            'relationshipLabel' => $this->relationshipLabel(
                $profile->relationship_stage
            ),

            'expressionLabel' => $this->expressionLabel(
                $profile->currentExpression?->name
            ),
        ]);
    }

    private function currentProfile(): UserCharacterProfile
    {
        /** @var User $user */
        $user = auth()->user();

        return UserCharacterProfile::query()
            ->whereKey($this->profileId)
            ->where('user_id', $user->id)
            ->with([
                'character',
                'currentExpression',
            ])
            ->firstOrFail();
    }

    private function conversationForCurrentProfile(
        int $conversationId,
        string $ability
    ): Conversation {
        $conversation = Conversation::query()
            ->findOrFail($conversationId);

        Gate::authorize(
            $ability,
            $conversation
        );

        abort_unless(
            $conversation->user_character_profile_id === $this->profileId,
            404
        );

        return $conversation;
    }

    private function moodLabel(CharacterMood $mood): string
    {
        return match ($mood) {
            CharacterMood::Neutral => 'Neutral',
            CharacterMood::Happy => 'Feliz',
            CharacterMood::Sad => 'Triste',
            CharacterMood::Angry => 'Enojo',
            CharacterMood::Embarrassed => 'Vergüenza',
            CharacterMood::Surprised => 'Sorpresa',
            CharacterMood::Curious => 'Curiosidad',
        };
    }

    private function relationshipLabel(
        RelationshipStage $stage
    ): string {
        return match ($stage) {
            RelationshipStage::Strangers => 'Desconocidos',
            RelationshipStage::Acquaintances => 'Conocidos',
            RelationshipStage::Friends => 'Amigos',
            RelationshipStage::CloseFriends => 'Amigos cercanos',
            RelationshipStage::RomanticInterest => 'Interés romántico',
            RelationshipStage::Partners => 'Pareja',
        };
    }

    private function expressionLabel(?string $expression): string
    {
        return match ($expression) {
            'neutral' => 'Neutral',
            'happy' => 'Feliz',
            'angry' => 'Enojo',
            'embarrassed' => 'Vergüenza',
            'sad' => 'Triste',
            'surprised' => 'Sorpresa',
            default => 'Sin expresión',
        };
    }
}
