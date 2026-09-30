/**
 * Mínimo 8 caracteres, con al menos una minúscula, una mayúscula,
 * un número y un símbolo. Sin espacios.
 */
export const PASSWORD_REGEX = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9\s])\S{8,64}$/;

export const PASSWORD_MESSAGE =
    'La contraseña debe tener entre 8 y 64 caracteres, sin espacios, e incluir una mayúscula, una minúscula, un número y un símbolo.';

/**
 * Devuelve la lista de errores de la contraseña (vacía si es válida).
 */
export function validatePassword(password, confirmation) {
    if (!password) {
        return ['La contraseña es obligatoria.'];
    }

    const errors = [];

    if (!PASSWORD_REGEX.test(password)) {
        errors.push(PASSWORD_MESSAGE);
    }

    if (password !== confirmation) {
        errors.push('Las contraseñas no coinciden.');
    }

    return errors;
}
