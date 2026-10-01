<?php

namespace App\Actions\Characters;

use App\Models\GeneratedAsset;
use Illuminate\Support\Facades\Storage;

final class DeleteCharacterAssets
{
    public function directoryFor(
        int $profileId
    ): string {
        return GeneratedAsset::directoryForProfile(
            $profileId
        );
    }

    public function execute(
        int $profileId
    ): void {
        Storage::disk(
            GeneratedAsset::DISK
        )->deleteDirectory(
            $this->directoryFor(
                $profileId
            )
        );
    }
}
