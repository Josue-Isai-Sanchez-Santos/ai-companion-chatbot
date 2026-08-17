<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return self::messageRules();
    }

    public function messages(): array
    {
        return self::messageValidationMessages();
    }

    public static function messageRules(): array
    {
        return [
            'message' => [
                'required',
                'string',
                'max:'.(int) config(
                    'chatbot.message_max_length',
                    4000
                ),
            ],
        ];
    }

    public static function messageValidationMessages(): array
    {
        return [
            'message.required' => 'Escribe un mensaje antes de enviarlo.',

            'message.string' => 'El mensaje debe ser texto.',

            'message.max' => 'El mensaje no puede superar :max caracteres.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'message' => trim(
                (string) $this->input('message')
            ),
        ]);
    }
}
