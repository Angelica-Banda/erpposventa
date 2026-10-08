<?php

namespace Database\Seeders;

use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Roles
        $rolAdministrador = Rol::firstOrCreate(
            ['nombre' => 'admin'],
            [
                'nombre_visible' => 'Administrador',
                'descripcion' => 'Acceso y control total de todos los módulos'
            ]
        );

        $rolVendedor = Rol::firstOrCreate(
            ['nombre' => 'vendedor'],
            [
                'nombre_visible' => 'Vendedor',
                'descripcion' => 'Acceso exclusivo al módulo de Ventas (POS)'
            ]
        );

        // 2. Usuario administrador
        $administrador = Usuario::updateOrCreate(
            ['email' => 'admin@posventa.com'],
            [
                'name' => 'Administrador',
                'password' => Hash::make('password'),
            ]
        );
        $administrador->roles()->sync([$rolAdministrador->id]);

        // También asignamos el rol administrador al usuario inicial de prueba.
        $usuarioPrueba = Usuario::where('email', 'test@example.com')->first();
        if ($usuarioPrueba) {
            $usuarioPrueba->roles()->sync([$rolAdministrador->id]);
        }

        // 3. Usuario Vendedor (solo acceso al módulo de ventas)
        $vendedor = Usuario::updateOrCreate(
            ['email' => 'vendedor@posventa.com'],
            [
                'name' => 'Carlos Vendedor',
                'password' => Hash::make('password'),
            ]
        );
        $vendedor->roles()->sync([$rolVendedor->id]);
    }
}
