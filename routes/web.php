<?php

use App\Livewire\Chat\ChatPage;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')
    ->name('home');

Route::livewire('/chat', ChatPage::class)
    ->middleware('auth')
    ->name('chat');
