<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ContentRequest;
use App\Models\Content;
use App\Services\GenreCategorizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * Catálogo de contenido multimedia guardado en MongoDB.
 *
 * Lectura pública (solo lo publicado) y escritura reservada a administradores.
 */
class ContentController extends Controller
{
    // GET /api/contents?type=serie&source=ia&genre=Drama&q=amor&per_page=20
    public function index(Request $request): JsonResponse
    {
        return response()->json($this->filtered($request, Content::published()));
    }

    // GET /api/contents/{id o slug}
    public function show(string $content): JsonResponse
    {
        return response()->json($this->find($content, onlyPublished: true));
    }

    // GET /api/admin/contents  (incluye lo no publicado)
    public function adminIndex(Request $request): JsonResponse
    {
        $query = Content::query();

        if ($request->filled('published')) {
            $query->where('published', $request->boolean('published'));
        }

        return response()->json($this->filtered($request, $query));
    }

    // POST /api/admin/contents
    public function store(ContentRequest $request): JsonResponse
    {
        $content = Content::create($request->validated());

        return response()->json($content, 201);
    }

    // PUT/PATCH /api/admin/contents/{id o slug}
    public function update(ContentRequest $request, string $content): JsonResponse
    {
        $model = $this->find($content);
        $model->update($request->validated());

        return response()->json($model);
    }

    // DELETE /api/admin/contents/{id o slug}
    public function destroy(string $content): Response
    {
        $this->find($content)->delete();

        return response()->noContent();
    }

    private function filtered(Request $request, $query)
    {
        foreach (['type', 'source', 'license', 'language'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->input($field));
            }
        }

        // genre=Comedia o genre=Comedia,Western (cualquiera de ellos). En MongoDB, "in" sobre un arreglo
        // busca documentos cuyo arreglo contenga alguno de los valores.
        if ($request->filled('genre')) {
            $query->whereIn('genres', array_map('trim', explode(',', $request->input('genre'))));
        }

        if ($request->filled('year_from')) {
            $query->where('year', '>=', $request->integer('year_from'));
        }

        if ($request->filled('year_to')) {
            $query->where('year', '<=', $request->integer('year_to'));
        }

        // Busca en el título y en las etiquetas originales (actores, épocas…).
        if ($request->filled('q')) {
            $regex = '/'.preg_quote($request->input('q'), '/').'/i';
            $query->where(fn ($q) => $q->where('title', 'regex', $regex)->orWhere('tags', 'regex', $regex));
        }

        [$field, $direction] = match ($request->input('sort')) {
            'year' => ['year', 'desc'],
            'oldest' => ['year', 'asc'],
            'title' => ['title', 'asc'],
            default => ['created_at', 'desc'],
        };

        $perPage = min(max($request->integer('per_page', 20), 1), 100);

        return $query->orderBy($field, $direction)->paginate($perPage);
    }

    // GET /api/contents/genres  =>  [{ "genre": "Comedia", "count": 7 }, ...]  (para armar filtros)
    public function genres(): JsonResponse
    {
        $counts = Content::published()->get(['genres'])
            ->flatMap(fn (Content $c) => (array) $c->genres)
            ->countBy();

        // Orden alfabético sin acentos ("Bélico" va junto a la B).
        $genres = collect(GenreCategorizer::GENRES)
            ->filter(fn ($genre) => $counts->has($genre))
            ->sortBy(fn ($genre) => Str::ascii($genre))
            ->map(fn ($genre) => ['genre' => $genre, 'count' => $counts[$genre]])
            ->values();

        return response()->json($genres);
    }

    private function find(string $idOrSlug, bool $onlyPublished = false): Content
    {
        $query = $onlyPublished ? Content::published() : Content::query();

        // Los _id de MongoDB son ObjectId de 24 caracteres hexadecimales; si no, se busca por slug.
        $field = preg_match('/^[a-f0-9]{24}$/i', $idOrSlug) ? '_id' : 'slug';

        return $query->where($field, $idOrSlug)->firstOrFail();
    }
}
