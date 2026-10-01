<?php

namespace App\Enums;

enum AssetType: string
{
    case Image = 'image';
    case Audio = 'audio';
    case Video = 'video';
    case Other = 'other';
}
