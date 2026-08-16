<?php

namespace App\Enums;

enum MemoryType: string
{
    case UserFact = 'user_fact';
    case UserPreference = 'user_preference';
    case CharacterFact = 'character_fact';
    case SharedEvent = 'shared_event';
    case Promise = 'promise';
    case RelationshipEvent = 'relationship_event';
    case WorldFact = 'world_fact';
    case TemporaryContext = 'temporary_context';
}
