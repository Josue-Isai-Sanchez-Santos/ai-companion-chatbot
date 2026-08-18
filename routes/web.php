<?php

use App\Http\Controllers\StreamMessageController;
use App\Livewire\Chat\ChatPage;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')
    ->name('home');

Route::middleware('auth')
    ->group(function (): void {
        Route::livewire(
            '/chat',
            ChatPage::class
        )->name('chat');

        Route::post(
            '/chat/stream',
            [
                StreamMessageController::class,
                'stream',
            ]
        )->name('chat.stream');

        Route::post(
            '/chat/stream/retry',
            [
                StreamMessageController::class,
                'retry',
            ]
        )->name('chat.stream.retry');
    });
