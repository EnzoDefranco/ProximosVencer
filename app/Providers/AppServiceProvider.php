<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AppServiceProvider extends ServiceProvider
{
    public const HOME = '/items'; // <<<<<<<<<<

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::define('validar-vencimientos', function ($user) {
            // Ajustá si tu columna se llama distinto
            return in_array($user->role ?? '', ['admin', 'deposito']);
        });

        Gate::define('eliminar-vencidos', function ($user) {
            return ($user->role ?? '') === 'admin';
        });
    }
}
