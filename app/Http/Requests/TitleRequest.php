<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TitleRequest extends FormRequest
{
    /**
     * La autorización real está en las rutas (middleware "auth").
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Las mismas reglas sirven para crear y editar: "duration" y "seasons"
     * dependen del tipo, así que se revisan aquí en un solo lugar.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $isMovie = $this->input('type') === 'Película';

        return [
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['Película', 'Serie'])],
            'genre' => ['required', 'string', 'max:100'],
            'year' => ['required', 'integer', 'min:1900', 'max:2099'],
            'description' => ['nullable', 'string'],
            'maturity' => ['required', Rule::in(['TP', '7+', '13+', '16+', '18+'])],
            'colors' => ['nullable', 'array', 'max:5'],
            'colors.*' => ['string', 'max:20'],
            'duration' => [$isMovie ? 'required' : 'nullable', 'string', 'max:50'],
            'seasons' => [$isMovie ? 'nullable' : 'required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'El título es obligatorio.',
            'type.in' => 'El tipo debe ser Película o Serie.',
            'genre.required' => 'Selecciona un género.',
            'year.min' => 'El año debe ser 1900 o posterior.',
            'year.max' => 'El año debe ser 2099 o anterior.',
            'duration.required' => 'La duración es obligatoria para películas.',
            'seasons.required' => 'El número de temporadas es obligatorio para series.',
        ];
    }
}
