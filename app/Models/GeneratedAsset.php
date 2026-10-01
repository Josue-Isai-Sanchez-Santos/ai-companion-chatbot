<?php

namespace App\Models;

use App\Enums\AssetType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;

final class GeneratedAsset extends Model
{
    /*
     * Generated user assets are deliberately private.
     *
     * They must never be placed directly on the
     * publicly linked storage disk.
     */
    public const DISK = 'local';

    protected $fillable = [
        'user_character_profile_id',
        'type',
        'mime_type',
        'size_bytes',
    ];

    protected function casts(): array
    {
        return [
            'type' =>
                AssetType::class,

            'size_bytes' =>
                'integer',
        ];
    }

    public function userCharacterProfile(): BelongsTo
    {
        return $this->belongsTo(
            UserCharacterProfile::class
        );
    }

    public static function directoryForProfile(
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

    public static function generatePath(
        UserCharacterProfile $profile
    ): string {
        if (
            ! $profile->exists
            || $profile->id === null
        ) {
            throw new LogicException(
                'Generated assets require a persisted profile.'
            );
        }

        return sprintf(
            '%s/%s',
            self::directoryForProfile(
                $profile->id
            ),
            (string) Str::ulid()
        );
    }

    public static function registerForProfile(
        UserCharacterProfile $profile,
        AssetType $type,
        ?string $mimeType = null,
        ?int $sizeBytes = null
    ): self {
        if (
            $sizeBytes !== null
            && $sizeBytes < 0
        ) {
            throw new InvalidArgumentException(
                'Asset size cannot be negative.'
            );
        }

        $asset = new self([
            'user_character_profile_id' =>
                $profile->id,

            'type' =>
                $type,

            'mime_type' =>
                $mimeType,

            'size_bytes' =>
                $sizeBytes,
        ]);

        /*
         * path is deliberately NOT fillable.
         *
         * It can only be assigned from our own
         * generated path.
         */
        $asset->forceFill([
            'path' =>
                self::generatePath(
                    $profile
                ),
        ]);

        $asset->save();

        return $asset;
    }

    public function ownedBy(
        User $user
    ): bool {
        return $this
            ->userCharacterProfile()
            ->where(
                'user_id',
                $user->id
            )
            ->exists();
    }

    public function scopeOwnedBy(
        Builder $query,
        User $user
    ): Builder {
        return $query->whereHas(
            'userCharacterProfile',
            function (
                Builder $query
            ) use (
                $user
            ): void {
                $query->where(
                    'user_id',
                    $user->id
                );
            }
        );
    }

    public function storageDisk(): string
    {
        return self::DISK;
    }
}
