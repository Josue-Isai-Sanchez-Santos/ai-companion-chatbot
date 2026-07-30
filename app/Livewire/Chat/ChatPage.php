<?php

namespace App\Livewire\Chat;

use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Chat')]
class ChatPage extends Component
{
    public function render()
    {
        return view('livewire.chat.chat-page');
    }
}
