<?php

namespace App\Support;

class PasswordPolicy
{
    /**
     * Mínimo 8 caracteres, con al menos una minúscula, una mayúscula,
     * un número y un símbolo. Sin espacios.
     */
    public const REGEX = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9\s])\S{8,64}$/';

    /**
     * @return array<int, string>
     */
    public static function rules(): array
    {
        return ['required', 'string', 'regex:'.self::REGEX, 'confirmed'];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'password.required' => 'La contraseña es obligatoria.',
            'password.regex' => 'La contraseña debe tener entre 8 y 64 caracteres, sin espacios, e incluir una mayúscula, una minúscula, un número y un símbolo.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
        ];
    }
}
