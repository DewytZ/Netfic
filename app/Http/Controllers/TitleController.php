<?php

namespace App\Http\Controllers;

use App\Http\Requests\TitleRequest;
use App\Models\Title;

class TitleController extends Controller
{
    // GET. Devuelve todos los títulos.
    public function index()
    {
        return response()->json(Title::orderBy('id')->get());
    }

    // POST. Valida los datos y crea un título nuevo. Responde 201 con el registro creado.
    public function store(TitleRequest $request)
    {
        $title = Title::create($request->validated());

        return response()->json($title, 201);
    }

    // PUT/PATCH. Actualiza un título. Laravel busca el registro por su id (route model binding).
    public function update(TitleRequest $request, Title $title)
    {
        $data = $request->validated();

        // Si cambia de Serie a Película no debe quedar un número de temporadas, y al revés.
        if ($data['type'] === 'Película') {
            $data['seasons'] = null;
        } else {
            $data['duration'] = null;
        }

        $title->update($data);

        return response()->json($title);
    }

    // DELETE. Elimina un título. Responde 204 (sin contenido).
    public function destroy(Title $title)
    {
        $title->delete();

        return response()->noContent();
    }
}
