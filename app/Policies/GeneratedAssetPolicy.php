<?php

namespace App\Policies;

use App\Models\GeneratedAsset;
use App\Models\User;
use App\Models\UserCharacterProfile;

final class GeneratedAssetPolicy
{
    public function viewAny(
        User $user,
        UserCharacterProfile $profile
    ): bool {
        return $profile->user_id
            === $user->id;
    }

    public function view(
        User $user,
        GeneratedAsset $asset
    ): bool {
        return $asset->ownedBy(
            $user
        );
    }

    public function create(
        User $user,
        UserCharacterProfile $profile
    ): bool {
        return $profile->user_id
            === $user->id;
    }

    public function update(
        User $user,
        GeneratedAsset $asset
    ): bool {
        /*
         * Generated assets are immutable records.
         */
        return false;
    }

    public function delete(
        User $user,
        GeneratedAsset $asset
    ): bool {
        return $asset->ownedBy(
            $user
        );
    }
}
