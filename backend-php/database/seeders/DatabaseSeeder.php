<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Crea el usuario administrador solo si no existe
        User::firstOrCreate(
            ['email' => 'admin@istore.com'],
            [
                'name' => 'Bairon Admin',
                'password' => Hash::make('12345678'),
                'rol' => 'admin',
            ]
        );
    }
}