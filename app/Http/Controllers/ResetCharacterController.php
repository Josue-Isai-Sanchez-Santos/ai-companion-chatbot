<?php

namespace App\Http\Controllers;

use App\Actions\Characters\ResetCharacterAction;
use App\Http\Requests\ResetCharacterRequest;
use App\Models\User;
use App\Models\UserCharacterProfile;
use Illuminate\Http\RedirectResponse;

final class ResetCharacterController extends Controller
{
    public function __invoke(
        ResetCharacterRequest $request,
        ResetCharacterAction $resetCharacter
    ): RedirectResponse {
        $user =
            $request->user();

        abort_unless(
            $user instanceof User,
            401
        );

        $profile =
            UserCharacterProfile::query()
                ->findOrFail(
                    $request->integer(
                        'profile_id'
                    )
                );

        $result =
            $resetCharacter
                ->execute(
                    $user,
                    $profile
                );

        $redirect =
            redirect()
                ->route('chat')
                ->with(
                    'status',
                    'El personaje fue restablecido correctamente.'
                );

        if (
            ! $result[
                'assets_deleted'
            ]
        ) {
            $redirect->with(
                'warning',
                'El personaje fue restablecido, pero algunos archivos generados no pudieron eliminarse. El error quedó registrado.'
            );
        }

        return $redirect;
    }
}
