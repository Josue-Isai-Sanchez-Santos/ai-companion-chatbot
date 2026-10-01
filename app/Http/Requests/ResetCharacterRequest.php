<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ResetCharacterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()
            !== null;
    }

    public function rules(): array
    {
        return [
            'profile_id' => [
                'required',
                'integer',
                'exists:user_character_profiles,id',
            ],

            'confirmation' => [
                'required',
                'string',

                Rule::in([
                    (string) config(
                        'chatbot.reset_confirmation',
                        'BORRAR'
                    ),
                ]),
            ],
        ];
    }

    public function messages(): array
    {
        $confirmation =
            (string) config(
                'chatbot.reset_confirmation',
                'BORRAR'
            );

        return [
            'profile_id.required' =>
                'No se indicó el perfil a restablecer.',

            'profile_id.exists' =>
                'El perfil seleccionado ya no existe.',

            'confirmation.required' =>
                "Escribe {$confirmation} para confirmar.",

            'confirmation.in' =>
                "Debes escribir exactamente {$confirmation}.",
        ];
    }
}
