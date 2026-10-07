<?php

namespace App\Auth;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Igual que el proveedor "eloquent", pero guarda en caché el usuario de la sesión
 * para no consultarlo en la base de datos en cada petición.
 *
 * La caché se borra cada vez que el usuario se guarda o se elimina (ver User::booted),
 * así que un cambio de contraseña se nota en la siguiente petición.
 */
class CachedUserProvider extends EloquentUserProvider
{
    public const TTL_SECONDS = 300;

    public static function cacheKey(int|string $identifier): string
    {
        return "auth-user:{$identifier}";
    }

    /**
     * Guarda al usuario recién autenticado, para que la siguiente petición no tenga que buscarlo.
     */
    public static function remember(Authenticatable $user): void
    {
        if ($user instanceof Model) {
            Cache::put(self::cacheKey($user->getAuthIdentifier()), $user->getAttributes(), self::TTL_SECONDS);
        }
    }

    public static function forget(int|string $identifier): void
    {
        Cache::forget(self::cacheKey($identifier));
    }

    /**
     * @param  mixed  $identifier
     */
    public function retrieveById($identifier): ?Authenticatable
    {
        // Se guardan solo los atributos: la caché no admite objetos (cache.serializable_classes).
        $attributes = Cache::remember(
            self::cacheKey($identifier),
            self::TTL_SECONDS,
            fn () => parent::retrieveById($identifier)?->getAttributes(),
        );

        if ($attributes === null) {
            Cache::forget(self::cacheKey($identifier));

            return null;
        }

        $model = $this->createModel();

        return $model->newFromBuilder($attributes, $model->getConnection()->getName());
    }

    /**
     * @param  mixed  $identifier
     * @param  string  $token
     */
    public function retrieveByToken($identifier, #[\SensitiveParameter] $token): ?Authenticatable
    {
        $user = $this->retrieveById($identifier);
        $rememberToken = $user?->getRememberToken();

        return $rememberToken && hash_equals($rememberToken, $token) ? $user : null;
    }
}
