<?php

namespace App\Ai\Safety;

use App\Http\Requests\SendMessageRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class InputValidator
{
    public function validateMessage(
        string $content
    ): string {
        $content = str_replace(
            [
                "\r\n",
                "\r",
            ],
            "\n",
            $content
        );

        if (
            ! mb_check_encoding(
                $content,
                'UTF-8'
            )
        ) {
            throw ValidationException::withMessages([
                'message' =>
                    'El mensaje contiene texto inválido.',
            ]);
        }

        /*
         * Tabs and line feeds are allowed.
         * Other ASCII control characters are not.
         */
        if (
            preg_match(
                '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/',
                $content
            ) === 1
        ) {
            throw ValidationException::withMessages([
                'message' =>
                    'El mensaje contiene caracteres de control no permitidos.',
            ]);
        }

        $validated =
            Validator::make(
                [
                    'message' =>
                        trim(
                            $content
                        ),
                ],
                SendMessageRequest::messageRules(),
                SendMessageRequest::messageValidationMessages()
            )->validate();

        return $validated[
            'message'
        ];
    }
}
