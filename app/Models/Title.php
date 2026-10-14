<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Title extends Model
{
    use HasFactory;

    //los campos que permitimos llenar al crear o editar una pelicula
    protected   $fillable = [
    'title',
    'type',
    'genre',
    'year',
    'duration',
    'description',
    'maturity',
    'colors',
    'seasons',
];

    //cambiar la columna de JSON a arreglo
    protected $casts = [
        'colors' => 'array',
        'year' => 'integer',
    ];
}
