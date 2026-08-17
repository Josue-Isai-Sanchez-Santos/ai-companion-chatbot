<?php

namespace App\Livewire\Chat;

use App\Actions\Characters\CreateUserCharacterProfileAction;
use App\Enums\CharacterMood;
use App\Enums\RelationshipStage;
use App\Models\Character;
use App\Models\User;
use App\Models\UserCharacterProfile;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Chat')]
class ChatPage extends Component
{
    public int $profileId;

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
    }

    public function render(): View
    {
        /** @var User $user */
        $user = auth()->user();

        $profile = UserCharacterProfile::query()
            ->whereKey($this->profileId)
            ->where('user_id', $user->id)
            ->with([
                'character',
                'currentExpression',
            ])
            ->firstOrFail();

        return view('livewire.chat.chat-page', [
            'profile' => $profile,
            'character' => $profile->character,
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
