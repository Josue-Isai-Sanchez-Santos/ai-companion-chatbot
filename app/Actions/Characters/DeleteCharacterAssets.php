<?php

namespace App\Actions\Characters;

use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

final class DeleteCharacterAssets
{
    public function directoryFor(
        int $profileId
    ): string {
        if ($profileId < 1) {
            throw new InvalidArgumentException(
                'Profile id must be positive.'
            );
        }

        return sprintf(
            'character-assets/profiles/%d',
            $profileId
        );
    }

    public function execute(
        int $profileId
    ): void {
        Storage::disk('public')
            ->deleteDirectory(
                $this->directoryFor(
                    $profileId
                )
            );
    }
}
