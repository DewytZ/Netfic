<?php

namespace App\Http\Requests\Auth;

use App\Support\PasswordPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class ResetPasswordRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['email' => Str::lower((string) $this->input('email'))]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => PasswordPolicy::rules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'token.required' => 'Falta el token de restablecimiento.',
            'email.required' => 'El email no es válido.',
            'email.email' => 'El email no es válido.',
            ...PasswordPolicy::messages(),
        ];
    }

    /**
     * Con errores se vuelve al formulario del mismo enlace (o a pedir uno nuevo si falta el token).
     */
    protected function getRedirectUrl(): string
    {
        $token = (string) $this->input('token');

        return $token === '' ? route('password.request') : route('password.reset', ['token' => $token]);
    }
}
