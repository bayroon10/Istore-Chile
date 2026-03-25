<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pedido extends Model
{
    use HasFactory;

    // Los campos que React nos puede enviar
    protected $fillable = [
        'cliente_nombre', 
        'cliente_email', 
        'cliente_telefono', 
        'direccion', 
        'metodo_envio', 
        'total', 
        'carrito', 
        'estado'
    ];

    // Le decimos a Laravel que convierta el JSON en un Array automáticamente
    protected $casts = [
        'carrito' => 'array',
    ];
}