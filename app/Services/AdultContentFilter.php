<?php

namespace App\Services;

/**
 * Detecta material para adultos (+18) por palabras clave en título, géneros y sinopsis.
 *
 * Se usa al importar (se descarta) y para limpiar lo que ya está guardado
 * (php artisan content:purge-adult). Si algo se cuela, agrega aquí la palabra.
 */
class AdultContentFilter
{
    // Raíces en inglés y español; se buscan al inicio de palabra ("sex" detecta "sexploitation", no "Sussex").
    private const KEYWORDS = [
        // Sexual / desnudos
        'sex', 'nud', 'naked', 'erotic', 'erótic', 'porn', 'xxx', 'strip', 'stag film', 'burlesque',
        'peeping tom', 'adult film', 'desnud', 'playboy', 'orgy', 'orgía',
        'seduct', 'seducc', 'lingerie', 'topless', 'softcore', 'hardcore',
        // Abuso y violencia gráfica
        'molest', 'rape', 'violaci', 'incest', 'abuso', 'gore', 'snuff', 'extremely graphic', 'graphic violence',
        'concentration camp',
    ];

    public function isAdult(?string $title, array|string|null $genres = [], ?string $synopsis = null, ?string $maturity = null): bool
    {
        if (in_array($maturity, ['18+', 'R', 'NC-17', 'X'], true)) {
            return true;
        }

        $text = mb_strtolower(implode(' ', [$title, implode(' ', (array) $genres), $synopsis]));

        foreach (self::KEYWORDS as $keyword) {
            if (preg_match('/\b'.preg_quote($keyword, '/').'/u', $text)) {
                return true;
            }
        }

        return false;
    }
}
