<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\ViewErrorBag;
use Livewire\Attributes\Locked;
use Livewire\Component;

final class ResetCharacterModal extends Component
{
    #[Locked]
    public int $profileId;

    public bool $open = false;

    public function mount(
        int $profileId
    ): void {
        $this->profileId =
            $profileId;

        /*
         * Re-open the modal after a normal HTTP
         * validation redirect so the user can see the
         * confirmation error.
         */
        $errors =
            session('errors');

        $this->open =
            $errors instanceof ViewErrorBag
            && (
                $errors->has(
                    'confirmation'
                )
                || $errors->has(
                    'profile_id'
                )
            );
    }

    public function openModal(): void
    {
        $this->open = true;
    }

    public function closeModal(): void
    {
        $this->open = false;
    }

    public function render(): View
    {
        return view(
            'livewire.reset-character-modal',
            [
                'confirmationWord' =>
                    (string) config(
                        'chatbot.reset_confirmation',
                        'BORRAR'
                    ),
            ]
        );
    }
}
