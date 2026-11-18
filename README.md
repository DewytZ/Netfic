# Netfic

Catálogo tipo Netflix hecho con **Laravel 13 + React 19 (Vite)**. Tiene registro e inicio de sesión, perfiles, un catálogo de películas y series, un reproductor y un panel de administración. También tiene una API con Sanctum para la app Android.

Usa **dos bases de datos**:

| Base | Qué guarda |
|---|---|
| **PostgreSQL** | Usuarios, perfiles, sesiones, tokens de la API y la tabla `titles` |
| **MongoDB** | El catálogo multimedia (`contents`): películas, series, novelas, cortos y documentales libres de copyright |

> 🐳 **La forma recomendada de correrlo es con Docker** (sección 1): levanta la app, PostgreSQL, MongoDB y un buzón de correo de prueba, crea las tablas y carga datos de ejemplo con un solo comando. La instalación manual está en la sección 2.

---

## 1. Con Docker (recomendado)

### Requisitos

Solo [Docker Desktop](https://www.docker.com/products/docker-desktop/) (Windows/Mac) o Docker Engine con Compose (Linux). No necesitas PHP, Composer, Node, PostgreSQL ni MongoDB instalados.

### Arrancar

```bash
git clone https://github.com/DewytZ/Netfic.git
cd Netfic
docker compose up --build
```

La primera vez tarda unos minutos (compila la extensión de MongoDB y el frontend de React). Cuando la consola muestre `Server running on [http://0.0.0.0:8000]`, abre **http://localhost:8000**.

No hace falta crear el `.env` ni correr `key:generate`, `migrate` o `db:seed`. Al arrancar, el contenedor:

1. Genera la `APP_KEY` la primera vez y la guarda en el volumen de `storage`.
2. Corre las migraciones en PostgreSQL.
3. Corre los seeders (`SEED_ON_START=true`). Puedes repetirlos sin que se dupliquen los datos.

### Qué se levanta

| Servicio | Contenedor | En tu equipo | Para qué |
|---|---|---|---|
| App Laravel + React | `netfic-app` | http://localhost:8000 | La aplicación |
| PostgreSQL 16 | `netfic-postgres` | `localhost:5433` (usuario, clave y base: `netfic`) | Usuarios, perfiles, sesiones, tokens y `titles` |
| MongoDB 7 | `netfic-mongo` | `mongodb://localhost:27018` | Catálogo multimedia (`contents`) |
| Mailpit | `netfic-mailpit` | http://localhost:8025 | Muestra los correos que manda la app (por ejemplo, "olvidé mi contraseña") |

Los puertos 5433 y 27018 no son los habituales para no chocar con un PostgreSQL o MongoDB que ya tengas instalado. Con esos datos te puedes conectar desde DBeaver, pgAdmin o MongoDB Compass.

### Datos de ejemplo (seeders)

| Seeder | Base | Qué crea |
|---|---|---|
| `UserSeeder` | PostgreSQL | Un administrador y un usuario normal con 3 perfiles |
| `TitleSeeder` | PostgreSQL | 6 títulos (películas y series) en la tabla `titles` |
| `ContentSeeder` | MongoDB | 14 películas y cortos de dominio público de Internet Archive, ya publicados y listos para reproducir |
| `FrutinovelaSeeder` | MongoDB | 11 frutinovelas de YouTube (solo metadatos, de [`database/seeders/data/frutinovelas.json`](database/seeders/data/frutinovelas.json)). Las recopilaciones largas quedan como novelas con capítulos. No necesita Python ni internet para sembrar |

**Cuentas de prueba:**

| Rol | Correo | Contraseña |
|---|---|---|
| Administrador | `admin@netfic.test` | `Admin123!` |
| Usuario | `usuario@netfic.test` | `Usuario123!` |

El administrador ve el **Panel de administración** en `/admin`.

### Comandos útiles

```bash
docker compose up -d --build        # arrancar en segundo plano
docker compose logs -f app          # ver los logs de la app
docker compose down                 # apagar (los datos se conservan)
docker compose down -v              # apagar y BORRAR todos los datos (bases y storage)
```

Los comandos de `artisan` se corren con `docker compose exec app ...`:

```bash
docker compose exec app php artisan db:seed                     # volver a correr los seeders
docker compose exec app php artisan migrate:fresh --seed        # borrar las tablas SQL y empezar de cero
docker compose exec app php artisan content:import-archive --limit=30   # traer más películas
docker compose exec app php artisan tinker
docker compose exec app php artisan test                        # pruebas (no tocan tus datos, ver sección 6)
```

> El código se copia dentro de la imagen, así que **después de cambiar código hay que reconstruir**: `docker compose up -d --build`.

Para que los seeders no corran en cada arranque, cambia `SEED_ON_START` a `"false"` en [`docker-compose.yml`](docker-compose.yml).

### Usar solo las bases de Docker

Si prefieres correr la app en tu equipo con `php artisan serve` y `npm run dev` (sección 2), puedes levantar solo las bases:

```bash
docker compose up -d postgres mongo mailpit
```

El `.env.example` ya apunta a ellas (`DB_PORT=5433` y `MONGODB_URI=mongodb://127.0.0.1:27018`).

---

## 2. Instalación manual (sin Docker)

### Requisitos

| Programa | Versión | Notas |
|---|---|---|
| PHP | 8.3 o más nuevo | Con las extensiones `pdo_pgsql`, `mongodb`, `mbstring` y `openssl` |
| Composer | 2 | |
| Node.js | 22 o más nuevo | |
| PostgreSQL | 14 o más nuevo | O el de Docker: `docker compose up -d postgres` |
| MongoDB | 6 o más nuevo | O el de Docker: `docker compose up -d mongo` |
| Python + `yt-dlp` | opcional | Solo para importar frutinovelas de YouTube |

**En Windows** lo más fácil es [Laravel Herd](https://herd.laravel.com), que ya trae PHP y Composer.

> ⚠️ **La extensión `mongodb` de PHP es obligatoria.** Sin ella `composer install` falla con `requires ext-mongodb`.
>
> - **Herd:** búscala en *Settings → PHP → Extensions*.
> - **PHP instalado a mano:** descarga la DLL de [PECL](https://pecl.php.net/package/mongodb) para tu versión de PHP, ponla en la carpeta `ext` y agrega `extension=mongodb` al `php.ini`.
> - **Mac/Linux:** `pecl install mongodb`.
>
> Para comprobar que quedó activa: `php -m | grep mongodb` (en PowerShell: `php -m | findstr mongodb`).

### Instalación

```bash
git clone https://github.com/DewytZ/Netfic.git
cd Netfic

composer install
npm install

cp .env.example .env          # Windows (PowerShell/cmd): copy .env.example .env
php artisan key:generate
```

#### Configurar el `.env`

El `.env.example` apunta por defecto al PostgreSQL y al MongoDB de Docker. Si usas los tuyos, cambia estos valores:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=netfic
DB_USERNAME=postgres
DB_PASSWORD=tu_clave
DB_SSLMODE=prefer

MONGODB_URI=mongodb://127.0.0.1:27017
MONGODB_DATABASE=netfic
```

#### Crear las tablas y cargar los datos de ejemplo

```bash
php artisan migrate --seed
```

En MongoDB no hay que crear nada: `ContentSeeder` y `FrutinovelaSeeder` llenan la colección `contents`. Las cuentas de prueba son las mismas que en Docker (ver [Datos de ejemplo](#datos-de-ejemplo-seeders)).

#### Correr el proyecto

Usa dos terminales:

```bash
npm run dev            # Vite: compila React y recarga al guardar
php artisan serve      # http://localhost:8000
```

Si usas Herd, el sitio también abre en `http://netfic.test` y no necesitas `php artisan serve`.

Para producción, en lugar de `npm run dev` corre `npm run build`.

---

## 3. Crear un administrador

Los seeders ya crean `admin@netfic.test`. Para volver administrador a otra cuenta, regístrala en `/register` y sube su rol:

```bash
php artisan tinker --execute="App\Models\User::where('email', 'tu@correo.com')->update(['role' => 'admin']);"
# Con Docker, antepón: docker compose exec app
```

Cierra sesión y vuelve a entrar. El menú del catálogo mostrará **Panel de administración** (`/admin`).

---

## 4. Llenar el catálogo

Los seeders dejan 14 películas y 11 frutinovelas listas. Para tener más tienes estas opciones. Con Docker, antepón `docker compose exec app` a cada comando.

### Opción A: importar películas de Internet Archive

Trae películas de dominio público (metadatos, póster y enlace al MP4) desde [archive.org](https://archive.org). No necesita nada extra.

```bash
# 20 películas de la colección "feature_films" (lo que hace por defecto)
php artisan content:import-archive

# Más películas
php artisan content:import-archive --limit=50

# Con una búsqueda en la sintaxis de archive.org
php artisan content:import-archive "collection:(feature_films) AND subject:(horror)" --limit=30

# Identificadores concretos (el final de la URL archive.org/details/<id>)
php artisan content:import-archive --id=his_girl_friday --id=TheGeneral1926
```

- Puedes repetirlo sin miedo: lo que ya se importó se actualiza, no se duplica.
- Lo que llega con **licencia desconocida se guarda sin publicar**. Revísalo en el panel de admin antes de publicarlo.

### Opción B: importar frutinovelas de YouTube

Solo guarda **metadatos**: el video nunca se descarga y se reproduce con el reproductor oficial de YouTube. Los **Shorts** se descartan, porque YouTube los inserta con su interfaz vertical de Shorts. Necesita Python con `yt-dlp`, que **no viene en la imagen de Docker**: hazlo desde la instalación manual o usa el JSON de más abajo.

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

**Sin Python** (o con Docker): alguien con Python exporta un JSON y tú lo importas.

```bash
# Quien tenga Python:
python scripts/youtube_catalog.py --search "frutinovelas" --max 10 --out frutinovelas.json

# Sin Python:
php artisan content:import-youtube --file=frutinovelas.json

# Con Docker: copia el archivo al contenedor y luego impórtalo
docker compose cp frutinovelas.json app:/tmp/frutinovelas.json
docker compose exec app php artisan content:import-youtube --file=/tmp/frutinovelas.json
```

### Opción C: agregar contenido a mano

En `/admin`, pestaña **Contenido multimedia**, botón **Agregar contenido**. Desde ahí también puedes editar, borrar y publicar u ocultar cualquier contenido.

### Mantenimiento del catálogo

```bash
# Sanitiza todo el catálogo y quita las frutinovelas duplicadas (--dry-run para solo ver los cambios)
php artisan content:clean --dry-run
php artisan content:clean

# Normaliza los géneros y estima la clasificación que falte (--dry-run para solo ver los cambios)
php artisan content:categorize

# Borra el contenido +18 que se haya colado (--dry-run para ver qué borraría)
php artisan content:purge-adult
```

Los importadores ya descartan el contenido para adultos. Además, el catálogo público nunca muestra contenido sin publicar ni marcado como `18+`.

**Sanitización automática.** Todo lo que se guarda en `contents` (importadores, panel de admin, API y seeders) pasa por `App\Services\ContentSanitizer`:

- Texto sin etiquetas HTML, sin caracteres de control ni invisibles (espacios de ancho cero, controles de dirección) y con los espacios normalizados.
- URLs: solo `http`/`https` bien formadas. Cualquier enlace de YouTube (`watch?v=`, `youtu.be/`, `/shorts/`) se guarda como `https://www.youtube-nocookie.com/embed/ID`, sin parámetros de rastreo.
- Capítulos sin repetidos, en orden y numerados de corrido dentro de cada temporada.

**Duplicados de frutinovelas.** `App\Services\ContentDeduplicator` corre solo al final de cada `content:import-youtube` (`--no-dedupe` para evitarlo) y con `content:clean`:

| Caso | Qué hace |
|---|---|
| Mismo título y mismo canal (por ejemplo, la temporada 1 y la 2 como listas separadas) | Las fusiona: los capítulos que falten pasan a la que se conserva |
| Mismo título y otro canal (una resubida) | Borra la copia y conserva la más completa |
| Un video suelto que ya es capítulo de otra novela | Lo borra, y el importador ya no lo vuelve a crear |

Para comparar títulos no se distinguen mayúsculas, tildes, emojis ni un "Temporada N" al final. Se conserva la que tiene más capítulos y, si empatan, la publicada, la que tiene póster y la más antigua.

**Reproductor de frutinovelas.** Usa el reproductor oficial de YouTube sin sus controles; los de Netfic van debajo. Las políticas de la API de YouTube **no permiten tapar, recortar ni ocultar** su reproductor (logo, título o marca de agua), así que nada se pone encima del video. Para que se sienta propio sin romper esas reglas:

- La API se precarga al abrir el catálogo, así que el video arranca casi al instante.
- Mientras carga se ve la carátula de Netfic, por detrás del iframe.
- Se pasa al siguiente capítulo, o se muestra la pantalla final de Netfic, un instante antes de que termine el video, así nunca aparecen los "videos sugeridos" de YouTube.
- Si el navegador bloquea el autoplay con sonido, el video arranca en silencio con un botón "Activar sonido" (en vez del botón rojo de YouTube).
- Tiene control de volumen, de velocidad (0.5x a 2x) y atajos de teclado (espacio, flechas, M, F, N). Los controles se desvanecen tras 3 segundos sin mover el mouse.

---

## 5. API

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

## 6. Pruebas

```bash
php artisan test
# Con Docker:
docker compose exec app php artisan test
```

Las pruebas usan SQLite en memoria (no tocan PostgreSQL) y la base MongoDB `netfic_testing`, separada de tu catálogo. Si MongoDB no está corriendo, esas pruebas se saltan.

---

## 7. Problemas comunes

| Error | Solución |
|---|---|
| `port is already allocated` al hacer `docker compose up` | Otro programa usa el puerto 8000, 5433, 27018 u 8025. Ciérralo o cambia el número de la izquierda en `docker-compose.yml` (por ejemplo `"8080:8000"` y entra por http://localhost:8080) |
| Cambié código y en Docker no se ve | `docker compose up -d --build` |
| Quiero borrar todo y empezar de cero en Docker | `docker compose down -v` y luego `docker compose up --build` |
| `requires ext-mongodb` al hacer `composer install` | Activa la extensión `mongodb` de PHP (ver [Requisitos](#requisitos-1)), o usa Docker |
| `No application encryption key has been specified` | Sin Docker: `php artisan key:generate` |
| La página se ve sin estilos, o hay un error de `Vite manifest not found` | Sin Docker: deja corriendo `npm run dev`, o corre `npm run build` |
| El catálogo sale vacío | MongoDB no está corriendo o no tiene contenido. Corre los seeders (`php artisan db:seed`, o con Docker `docker compose exec app php artisan db:seed`) o mira [Llenar el catálogo](#4-llenar-el-catálogo). Si MongoDB falla, el dashboard sigue abriendo pero sin ese contenido |
| `could not find driver` (pgsql) | Activa la extensión `pdo_pgsql` de PHP |
| `Connection refused` al puerto 5433 o 27018 sin Docker | Levanta las bases: `docker compose up -d postgres mongo` |
| `419 Page Expired` en el panel | Recarga la página: la sesión o el token CSRF caducaron |
| Los correos de "olvidé mi contraseña" no llegan | Con Docker se ven en http://localhost:8025 (Mailpit). Sin Docker, con `MAIL_MAILER=log` se guardan en `storage/logs/laravel.log`; para enviarlos de verdad configura SMTP (ver los comentarios en `.env.example`) |
| Una frutinovela muestra "No se pudo cargar el reproductor de YouTube" o "El video no arrancó" | Un bloqueador de anuncios, la protección contra rastreo (Firefox estricto, Brave) o la red están bloqueando `youtube.com`. Permite `youtube.com` y `youtube-nocookie.com` para `localhost`, o prueba en otro navegador. Las películas de Internet Archive no dependen de YouTube |
| Quiero otras frutinovelas en el seeder | `python scripts/youtube_catalog.py --search "frutinovelas completas" --max 12 --out database/seeders/data/frutinovelas.json` y vuelve a sembrar |
| `content:import-youtube` dice que no encuentra Python o `yt_dlp` | `pip install yt-dlp` y revisa `PYTHON_BIN` en el `.env`. En Docker no hay Python: usa la opción con `--file` |
