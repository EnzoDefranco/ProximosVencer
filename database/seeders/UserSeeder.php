<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Usuario Depósito (puede editar checks)
        User::updateOrCreate(
            ['email' => 'deposito@enro'],
            [
                'name' => 'Depósito',
                'password' => Hash::make('depo1234'), // ⚠️ Cambiar en producción
                'role' => 'deposito',
                'email_verified_at' => now(),
            ]
        );

        // Usuario Ventas (solo lectura)
        User::updateOrCreate(
            ['email' => 'ventas@enro'],
            [
                'name' => 'Ventas',
                'password' => Hash::make('ventas1234'), // ⚠️ Cambiar en producción
                'role' => 'ventas',
                'email_verified_at' => now(),
            ]
        );
    }
}
