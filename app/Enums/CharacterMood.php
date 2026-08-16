<?php

namespace App\Enums;

enum CharacterMood: string
{
    case Neutral = 'neutral';
    case Happy = 'happy';
    case Sad = 'sad';
    case Angry = 'angry';
    case Embarrassed = 'embarrassed';
    case Surprised = 'surprised';
    case Curious = 'curious';
}
