<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    // Esta línea es la "llave" para que el formulario funcione
    protected $fillable = ['nombre', 'categoria', 'precio', 'stock_actual', 'compatibilidad','imagen'];
}