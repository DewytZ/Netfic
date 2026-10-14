<?php

namespace App\Http\Controllers;

use App\Models\Title;
use Illuminate\Http\Request;

class TitleController extends Controller
{
    //1.- GET. devuelve todas las peliculas
    public function index()
    {
        return response()->json(Title::orderBy('id')->get());
    }

    //2.- POST. recibe todos los datos del formulario y crea un titulo nuevo en supabase
    public function store(Request $request)
{
    // --- Validación condicional ---
    $rules = [
        'title'       => 'required|string|max:255',
        'type'        => 'required|in:Película,Serie',
        'genre'       => 'required|string|max:100',
        'year'        => 'required|integer|min:1900|max:2099',
        'description' => 'nullable|string',
        'maturity'    => 'nullable|string|max:10',
        'colors'      => 'nullable|string',
    ];
    if ($request->type === 'Película') {
        $rules['duration'] = 'required|string|max:50';
        $rules['seasons']  = 'nullable|integer|min:1';
    } else { // Serie
        $rules['duration'] = 'nullable|string|max:50';
        $rules['seasons']  = 'required|integer|min:1';
    }
    $validated = $request->validate($rules);
    // -----------------------------------
    // se guarda en supabase
    $title = Title::create($validated);
    // devolvemos el título recién creado con código 201
    return response()->json($title, 201);
}

    
    //3. DELETE. elimina un titulo por su id 
    public function destroy(string $id)
    {
        $title = Title::findOrFail($id);
        $title->delete();

        return response()->json(['message' => 'Título eliminado con éxito']);
    }
}