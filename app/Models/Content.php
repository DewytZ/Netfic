<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use MongoDB\Laravel\Eloquent\Model;

/**
 * Contenido multimedia libre de copyright guardado en MongoDB (colección "contents").
 *
 * Cada documento es una película, serie, novela o corto. Las series y novelas
 * guardan sus capítulos embebidos en "episodes" para leerlos en una sola consulta.
 */
class Content extends Model
{
    // Tipos de contenido que maneja el catálogo.
    public const TYPES = ['pelicula', 'serie', 'novela', 'corto', 'documental'];

    // De dónde viene el contenido.
    public const SOURCE_INTERNET_ARCHIVE = 'internet_archive';
    public const SOURCE_FRUTINOVELA = 'frutinovela';
    public const SOURCE_AI = 'ia';
    public const SOURCES = [self::SOURCE_INTERNET_ARCHIVE, self::SOURCE_FRUTINOVELA, self::SOURCE_AI];

    // Licencias aceptadas. "desconocida" se guarda sin publicar hasta que un admin la revise.
    // "youtube-embed": el video es del canal; solo se muestra con el reproductor oficial de YouTube (nunca se descarga).
    public const LICENSES = ['dominio-publico', 'cc0', 'cc-by', 'cc-by-sa', 'original-ia', 'youtube-embed', 'desconocida'];

    protected $connection = 'mongodb';

    protected $table = 'contents';

    protected $fillable = [
        'title',
        'slug',
        'type',
        'source',
        'source_ref',   // { identifier, url, channel?, channel_url? } en el origen (Internet Archive, YouTube…)
        'license',
        'license_url',
        'synopsis',
        'genres',
        'tags',
        'year',
        'duration',     // minutos (películas y cortos)
        'language',
        'maturity',
        'poster_url',
        'backdrop_url',
        'video_url',    // MP4 directo (película o corto); las series usan episodes[].video_url
        'embed_url',    // reproductor insertado (YouTube); se usa cuando no hay video_url
        'episodes',     // [{ season, number, title, synopsis, duration, video_url, embed_url, video_id, start, end, thumbnail_url }]
        'ai',           // { model, prompt } cuando el contenido fue generado por IA
        'published',
    ];

    protected $casts = [
        'year' => 'integer',
        'duration' => 'integer',
        'published' => 'boolean',
    ];

    protected $attributes = [
        'published' => false,
    ];

    protected static function booted(): void
    {
        // Genera un slug único a partir del título si no viene uno.
        static::saving(function (Content $content) {
            if (blank($content->slug) && filled($content->title)) {
                $base = Str::slug($content->title);
                $slug = $base;
                $i = 2;

                while (static::where('slug', $slug)->where('_id', '!=', $content->getKey())->exists()) {
                    $slug = "{$base}-{$i}";
                    $i++;
                }

                $content->slug = $slug;
            }
        });
    }

    // Lo que ve el público: publicado y sin clasificación +18.
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('published', true)->where('maturity', '!=', '18+');
    }
}
