<?php

namespace App\Enums;

enum RelationshipStage: string
{
    case Strangers = 'strangers';
    case Acquaintances = 'acquaintances';
    case Friends = 'friends';
    case CloseFriends = 'close_friends';
    case RomanticInterest = 'romantic_interest';
    case Partners = 'partners';
}
