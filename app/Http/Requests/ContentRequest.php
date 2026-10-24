<?php

namespace App\Http\Requests;

use App\Models\Content;
use App\Services\GenreCategorizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validación de contenido multimedia para crear (POST) y editar (PUT/PATCH).
 * En PATCH los campos obligatorios pasan a ser opcionales para permitir ediciones parciales.
 */
class ContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $required = $this->isMethod('PATCH') ? 'sometimes' : 'required';

        return [
            'title' => [$required, 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash'],
            'type' => [$required, Rule::in(Content::TYPES)],
            'source' => [$required, Rule::in(Content::SOURCES)],
            'source_ref' => ['nullable', 'array'],
            'source_ref.identifier' => ['nullable', 'string', 'max:255'],
            'source_ref.url' => ['nullable', 'url'],
            'source_ref.channel' => ['nullable', 'string', 'max:255'],
            'source_ref.channel_url' => ['nullable', 'url'],
            'license' => [$required, Rule::in(Content::LICENSES)],
            'license_url' => ['nullable', 'url'],
            'synopsis' => ['nullable', 'string', 'max:5000'],
            'genres' => ['nullable', 'array'],
            'genres.*' => [Rule::in(GenreCategorizer::GENRES)],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
            'year' => ['nullable', 'integer', 'between:1880,'.(date('Y') + 1)],
            'duration' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'language' => ['nullable', 'string', 'max:10'],
            'maturity' => ['nullable', 'string', 'max:10'],
            'poster_url' => ['nullable', 'url'],
            'backdrop_url' => ['nullable', 'url'],
            'video_url' => ['nullable', 'url'],
            'embed_url' => ['nullable', 'url', 'starts_with:https://www.youtube-nocookie.com/embed/,https://www.youtube.com/embed/'],
            'episodes' => ['nullable', 'array'],
            'episodes.*.season' => ['required_with:episodes', 'integer', 'min:1'],
            'episodes.*.number' => ['required_with:episodes', 'integer', 'min:1'],
            'episodes.*.title' => ['required_with:episodes', 'string', 'max:255'],
            'episodes.*.synopsis' => ['nullable', 'string', 'max:2000'],
            'episodes.*.duration' => ['nullable', 'integer', 'min:1'],
            'episodes.*.video_url' => ['nullable', 'url'],
            'episodes.*.embed_url' => ['nullable', 'url', 'starts_with:https://www.youtube-nocookie.com/embed/,https://www.youtube.com/embed/'],
            'episodes.*.thumbnail_url' => ['nullable', 'url'],
            // Capítulos que son un tramo de un video de YouTube: segundo de inicio y de fin.
            'episodes.*.video_id' => ['nullable', 'string', 'regex:/^[\w-]{11}$/'],
            'episodes.*.start' => ['nullable', 'integer', 'min:0'],
            'episodes.*.end' => ['nullable', 'integer', 'gt:episodes.*.start'],
            'ai' => ['nullable', 'array'],
            'ai.model' => ['nullable', 'string', 'max:100'],
            'ai.prompt' => ['nullable', 'string', 'max:5000'],
            'published' => ['boolean'],
        ];
    }
}
