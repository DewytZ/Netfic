# Netfic

Catálogo tipo Netflix hecho con **Laravel 13 + React 19 (Vite)**. Tiene registro e inicio de sesión, perfiles, un catálogo de películas y series, un reproductor y un panel de administración. También tiene una API con Sanctum para la app Android.

Usa **dos bases de datos**:

| Base | Qué guarda |
|---|---|
| **PostgreSQL** (local o Supabase) | Usuarios, perfiles, sesiones, tokens de la API y la tabla `titles` |
| **MongoDB** (local o Atlas) | El catálogo multimedia (`contents`): películas, series, novelas, cortos y documentales libres de copyright |

---

## 1. Requisitos

| Programa | Versión | Notas |
|---|---|---|
| PHP | 8.3 o más nuevo | Con las extensiones `pdo_pgsql`, `mongodb`, `mbstring` y `openssl` |
| Composer | 2 | |
| Node.js | 22 o más nuevo | |
| PostgreSQL | 14 o más nuevo | O una base en [Supabase](https://supabase.com) |
| MongoDB | 6 o más nuevo | O un clúster gratis en [MongoDB Atlas](https://www.mongodb.com/atlas) |
| Python + `yt-dlp` | opcional | Solo para importar frutinovelas de YouTube |

**En Windows** lo más fácil es [Laravel Herd](https://herd.laravel.com), que ya trae PHP y Composer.

> ⚠️ **La extensión `mongodb` de PHP es obligatoria.** Sin ella `composer install` falla con `requires ext-mongodb`.
>
> - **Herd:** búscala en *Settings → PHP → Extensions*.
> - **PHP instalado a mano:** descarga la DLL de [PECL](https://pecl.php.net/package/mongodb) para tu versión de PHP, ponla en la carpeta `ext` y agrega `extension=mongodb` al `php.ini`.
> - **Mac/Linux:** `pecl install mongodb`.
>
> Para comprobar que quedó activa: `php -m | grep mongodb` (en PowerShell: `php -m | findstr mongodb`).

---

## 2. Instalación

```bash
git clone https://github.com/DewytZ/Netfic.git
cd Netfic

composer install
npm install

cp .env.example .env          # Windows (PowerShell/cmd): copy .env.example .env
php artisan key:generate
```

### Configurar el `.env`

**PostgreSQL local:**

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=netfic
DB_USERNAME=postgres
DB_PASSWORD=tu_clave
DB_SSLMODE=prefer
```

**Supabase** (base compartida del equipo): pide los datos de conexión al equipo por un canal privado. **Nunca los subas al repo.** Con Supabase cambia también:

```env
DB_SSLMODE=require
DB_PERSISTENT=true
```

**MongoDB:**

```env
# Local
MONGODB_URI=mongodb://127.0.0.1:27017
# Atlas
# MONGODB_URI=mongodb+srv://usuario:clave@cluster.xxxxx.mongodb.net
MONGODB_DATABASE=netfic
```

### Crear las tablas

```bash
php artisan migrate
```

En MongoDB no hay que crear nada: la colección `contents` aparece sola con el primer contenido que se guarda.

---

## 3. Correr el proyecto

Usa dos terminales:

```bash
npm run dev            # Vite: compila React y recarga al guardar
php artisan serve      # http://localhost:8000
```

Si usas Herd, el sitio también abre en `http://netfic.test` y no necesitas `php artisan serve`.

Para producción, en lugar de `npm run dev` corre `npm run build`.

---

## 4. Crear un administrador

La app no tiene una pantalla para volverse admin. Regístrate normal en `/register` y después sube tu rol a admin:

```bash
php artisan tinker --execute="App\Models\User::where('email', 'tu@correo.com')->update(['role' => 'admin']);"
```

Cierra sesión y vuelve a entrar. El menú del catálogo mostrará **Panel de administración** (`/admin`).

---

## 5. Llenar el catálogo

Con un MongoDB nuevo el catálogo **arranca vacío**. Tienes cuatro opciones:

### Opción A: usar el MongoDB Atlas del equipo

Pon en tu `.env` el mismo `MONGODB_URI` que usa el equipo y verás el catálogo que ya existe. Pide la URI por un canal privado.

### Opción B: importar películas de Internet Archive

Trae películas de dominio público (metadatos, póster y enlace al MP4) desde [archive.org](https://archive.org). No necesita nada extra.

```bash
# 20 películas de la colección "feature_films" (lo que hace por defecto)
php artisan content:import-archive

# Más películas
php artisan content:import-archive --limit=50

# Con una búsqueda en la sintaxis de archive.org
php artisan content:import-archive "collection:(feature_films) AND subject:(horror)" --limit=30

# Identificadores concretos (el final de la URL archive.org/details/<id>)
php artisan content:import-archive --id=nosferatu_1922 --id=night_of_the_living_dead
```

- Puedes repetirlo sin miedo: lo que ya se importó se actualiza, no se duplica.
- Lo que llega con **licencia desconocida se guarda sin publicar**. Revísalo en el panel de admin antes de publicarlo.

### Opción C: importar frutinovelas de YouTube

Solo guarda **metadatos**: el video nunca se descarga y se reproduce con el reproductor oficial de YouTube. Necesita Python con `yt-dlp`:

```bash
pip install yt-dlp
```

Si `python` no es el nombre del ejecutable en tu equipo, indícalo en el `.env` (por ejemplo `PYTHON_BIN=py` o `PYTHON_BIN=python3`).

```bash
# 10 videos sueltos (búsqueda por defecto: "frutinovelas completas")
php artisan content:import-youtube

# Series con capítulos (listas de reproducción del canal)
php artisan content:import-youtube --series --max=5

# Una lista de reproducción concreta, que se importa como una novela con capítulos
php artisan content:import-youtube --playlist="https://www.youtube.com/playlist?list=..."

# Otra búsqueda
php artisan content:import-youtube "frutinovela de mango" --max=20
```

**Sin Python:** otra persona del equipo puede exportar un JSON y tú lo importas.

```bash
# Quien tenga Python:
python scripts/youtube_catalog.py --search "frutinovelas" --max 10 --out frutinovelas.json

# Quien no lo tenga:
php artisan content:import-youtube --file=frutinovelas.json
```

### Opción D: agregar contenido a mano

En `/admin`, pestaña **Contenido multimedia**, botón **Agregar contenido**. Desde ahí también puedes editar, borrar y publicar u ocultar cualquier contenido.

### Mantenimiento del catálogo

```bash
# Normaliza los géneros y estima la clasificación que falte (--dry-run para solo ver los cambios)
php artisan content:categorize

# Borra el contenido +18 que se haya colado (--dry-run para ver qué borraría)
php artisan content:purge-adult
```

Los importadores ya descartan el contenido para adultos. Además, el catálogo público nunca muestra contenido sin publicar ni marcado como `18+`.

---

## 6. API

Las rutas de la API están en [`routes/api.php`](routes/api.php) y todas empiezan con `/api`.

**Autenticación** (la usa la app Android): `POST /login` y `POST /register` devuelven un token. Ese token va en la cabecera `Authorization: Bearer <token>` para `GET /user` y `POST /logout`.

**Contenido multimedia (público):** solo devuelve lo publicado y que no sea `18+`.

| Ruta | Qué hace |
|---|---|
| `GET /api/contents` | Lista paginada. Filtros: `type`, `source`, `license`, `language`, `genre` (varios separados por coma), `year_from`, `year_to`, `q` (busca en título y etiquetas), `sort` (`year`, `oldest`, `title`), `per_page` (máximo 100) |
| `GET /api/contents/genres` | Géneros con cuántos contenidos tiene cada uno |
| `GET /api/contents/{id o slug}` | Un contenido con sus capítulos |

Ejemplo: `GET /api/contents?type=novela&genre=Drama,Romance&sort=year`

**Contenido multimedia (admin):** requiere un usuario con rol admin.

- Con token Bearer: `/api/admin/contents`
- Con la sesión del navegador (lo que usa el panel): `/admin-api/contents`

Las dos aceptan `GET` (incluye lo no publicado), `POST`, `PUT`/`PATCH /{id}` y `DELETE /{id}`.

**Títulos (base SQL):** `GET /api/titles`.

---

## 7. Pruebas

```bash
php artisan test
```

Las pruebas de contenido usan la base MongoDB `netfic_testing`, separada de tu catálogo. Si MongoDB no está corriendo, esas pruebas se saltan.

---

## 8. Problemas comunes

| Error | Solución |
|---|---|
| `requires ext-mongodb` al hacer `composer install` | Activa la extensión `mongodb` de PHP (ver [Requisitos](#1-requisitos)) |
| `No application encryption key has been specified` | `php artisan key:generate` |
| La página se ve sin estilos, o hay un error de `Vite manifest not found` | Deja corriendo `npm run dev`, o corre `npm run build` |
| El catálogo sale vacío | MongoDB no está corriendo o no tiene contenido (ver [Llenar el catálogo](#5-llenar-el-catálogo)). Si MongoDB falla, el dashboard sigue abriendo pero sin ese contenido |
| `could not find driver` (pgsql) | Activa la extensión `pdo_pgsql` de PHP |
| `SSL connection is required` con Supabase | `DB_SSLMODE=require` |
| `419 Page Expired` en el panel | Recarga la página: la sesión o el token CSRF caducaron |
| Los correos de "olvidé mi contraseña" no llegan | Con `MAIL_MAILER=log` se guardan en `storage/logs/laravel.log`. Para enviarlos de verdad configura SMTP (ver los comentarios en `.env.example`) |
| `content:import-youtube` dice que no encuentra Python o `yt_dlp` | `pip install yt-dlp` y revisa `PYTHON_BIN` en el `.env` |

> **Docker:** el `Dockerfile` todavía no instala la extensión `mongodb` y `docker-compose.yml` está vacío. Por ahora, usa la instalación local.
