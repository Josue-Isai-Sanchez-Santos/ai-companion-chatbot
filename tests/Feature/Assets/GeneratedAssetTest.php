<?php

namespace Tests\Feature\Assets;

use App\Actions\Characters\CreateUserCharacterProfileAction;
use App\Actions\Characters\ResetCharacterAction;
use App\Enums\AssetType;
use App\Models\Character;
use App\Models\GeneratedAsset;
use App\Models\User;
use App\Models\UserCharacterProfile;
use Database\Seeders\CharacterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GeneratedAssetTest extends TestCase
{
    use RefreshDatabase;

    private Character $character;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(
            'local'
        );

        Storage::fake(
            'public'
        );

        $this->seed(
            CharacterSeeder::class
        );

        $this->character =
            Character::query()
                ->where(
                    'slug',
                    'default-companion'
                )
                ->firstOrFail();
    }

    private function profileFor(
        User $user
    ): UserCharacterProfile {
        return app(
            CreateUserCharacterProfileAction::class
        )->execute(
            $user,
            $this->character
        );
    }

    public function test_asset_can_be_registered_and_stored_privately(): void
    {
        $user =
            User::factory()
                ->create();

        $profile =
            $this->profileFor(
                $user
            );

        $asset =
            GeneratedAsset::registerForProfile(
                $profile,
                AssetType::Other,
                mimeType:
                    'text/plain',
                sizeBytes:
                    4
            );

        Storage::disk(
            $asset->storageDisk()
        )->put(
            $asset->path,
            'TEST'
        );

        $this->assertDatabaseHas(
            'generated_assets',
            [
                'id' =>
                    $asset->id,

                'user_character_profile_id' =>
                    $profile->id,

                'type' =>
                    AssetType::Other->value,

                'path' =>
                    $asset->path,

                'mime_type' =>
                    'text/plain',

                'size_bytes' =>
                    4,
            ]
        );

        Storage::disk('local')
            ->assertExists(
                $asset->path
            );

        /*
         * Nothing is written to the publicly linked
         * storage disk.
         */
        Storage::disk('public')
            ->assertMissing(
                $asset->path
            );
    }

    public function test_asset_path_is_generated_inside_own_profile_directory(): void
    {
        $user =
            User::factory()
                ->create();

        $profile =
            $this->profileFor(
                $user
            );

        $asset =
            GeneratedAsset::registerForProfile(
                $profile,
                AssetType::Image
            );

        $this->assertStringStartsWith(
            GeneratedAsset::directoryForProfile(
                $profile->id
            ).'/',
            $asset->path
        );

        $filename =
            basename(
                $asset->path
            );

        $this->assertMatchesRegularExpression(
            '/^[0-9A-HJKMNP-TV-Z]{26}$/',
            $filename
        );

        $this->assertStringNotContainsString(
            '..',
            $asset->path
        );

        $this->assertStringNotContainsString(
            '\\',
            $asset->path
        );
    }

    public function test_mass_assignment_cannot_replace_asset_path(): void
    {
        $user =
            User::factory()
                ->create();

        $profile =
            $this->profileFor(
                $user
            );

        $asset =
            GeneratedAsset::registerForProfile(
                $profile,
                AssetType::Other
            );

        $originalPath =
            $asset->path;

        $asset->fill([
            'path' =>
                '../../outside.txt',
        ])->save();

        $asset->refresh();

        $this->assertSame(
            $originalPath,
            $asset->path
        );

        $this->assertNotSame(
            '../../outside.txt',
            $asset->path
        );
    }

    public function test_owner_can_query_asset_but_another_user_cannot(): void
    {
        $owner =
            User::factory()
                ->create();

        $other =
            User::factory()
                ->create();

        $ownerProfile =
            $this->profileFor(
                $owner
            );

        $this->profileFor(
            $other
        );

        $asset =
            GeneratedAsset::registerForProfile(
                $ownerProfile,
                AssetType::Audio
            );

        $this->assertTrue(
            $asset->ownedBy(
                $owner
            )
        );

        $this->assertFalse(
            $asset->ownedBy(
                $other
            )
        );

        $this->assertNotNull(
            GeneratedAsset::query()
                ->ownedBy(
                    $owner
                )
                ->find(
                    $asset->id
                )
        );

        $this->assertNull(
            GeneratedAsset::query()
                ->ownedBy(
                    $other
                )
                ->find(
                    $asset->id
                )
        );
    }

    public function test_reset_removes_asset_record_and_physical_file(): void
    {
        $user =
            User::factory()
                ->create();

        $profile =
            $this->profileFor(
                $user
            );

        $oldProfileId =
            $profile->id;

        $asset =
            GeneratedAsset::registerForProfile(
                $profile,
                AssetType::Video,
                mimeType:
                    'video/mp4',
                sizeBytes:
                    8
            );

        Storage::disk('local')
            ->put(
                $asset->path,
                'FAKE_MP4'
            );

        $assetId =
            $asset->id;

        $assetPath =
            $asset->path;

        app(
            ResetCharacterAction::class
        )->execute(
            $user,
            $profile
        );

        $this->assertDatabaseMissing(
            'generated_assets',
            [
                'id' =>
                    $assetId,
            ]
        );

        Storage::disk('local')
            ->assertMissing(
                $assetPath
            );

        $this->assertDatabaseMissing(
            'user_character_profiles',
            [
                'id' =>
                    $oldProfileId,
            ]
        );

        $this->assertSame(
            1,
            $user
                ->characterProfiles()
                ->count()
        );
    }

    public function test_profile_exposes_only_its_generated_assets(): void
    {
        $firstUser =
            User::factory()
                ->create();

        $secondUser =
            User::factory()
                ->create();

        $firstProfile =
            $this->profileFor(
                $firstUser
            );

        $secondProfile =
            $this->profileFor(
                $secondUser
            );

        $firstAsset =
            GeneratedAsset::registerForProfile(
                $firstProfile,
                AssetType::Image
            );

        $secondAsset =
            GeneratedAsset::registerForProfile(
                $secondProfile,
                AssetType::Image
            );

        $this->assertSame(
            [
                $firstAsset->id,
            ],
            $firstProfile
                ->generatedAssets()
                ->pluck('id')
                ->all()
        );

        $this->assertNotContains(
            $secondAsset->id,
            $firstProfile
                ->generatedAssets()
                ->pluck('id')
                ->all()
        );
    }
}
