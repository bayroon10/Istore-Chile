<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/reseteo-maestro', function () {
    // Esto borra la base de datos, la vuelve a crear con la columna 'rol' y siembra al Admin
    Artisan::call('migrate:fresh', ['--seed' => true, '--force' => true]);
    return '¡Base de datos formateada y Admin sembrado con éxito! 🚀';
});