<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\FichajesController;
use App\Http\Controllers\ItemController;

Route::get('/', fn() => redirect('/items'));

Route::get('/dashboard', fn() => view('dashboard'))
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | ITEMS
    |--------------------------------------------------------------------------
    */
    Route::get('/items', [ItemController::class, 'index'])
        ->name('items.index');

    Route::get('/items/exportar', [ItemController::class, 'exportar'])
        ->name('items.exportar');

    Route::post('/items/confirmar', [ItemController::class, 'confirmar'])
        ->middleware('can:validar-vencimientos')
        ->name('items.confirmar');

    Route::get('/items/{codigo}/historial', [ItemController::class, 'historial'])
        ->name('items.historial');

    Route::post('/items/print', [ItemController::class, 'imprimirPendientes'])
        ->name('items.imprimir');

    // Vencidos
    Route::get('/vencidos', [ItemController::class, 'vencidos'])->name('vencidos.index');
    Route::post('/vencidos/print', [ItemController::class, 'imprimirVencidos'])->name('vencidos.print');
    Route::delete('/vencidos/{id}', [ItemController::class, 'destroyVencido'])->name('vencidos.destroy');
    /*
    |--------------------------------------------------------------------------
    | FICHAJES
    |--------------------------------------------------------------------------
    */
    Route::get('/fichajes', [FichajesController::class, 'index'])
        ->name('fichajes.index');

    Route::get('/fichajes/detalle/{empleado}', [FichajesController::class, 'detalle'])
        ->name('fichajes.detalle');

    /*
    |--------------------------------------------------------------------------
    | PERFIL
    |--------------------------------------------------------------------------
    */
    Route::get('/profile', [ProfileController::class, 'edit'])
        ->name('profile.edit');

    Route::patch('/profile', [ProfileController::class, 'update'])
        ->name('profile.update');

    Route::delete('/profile', [ProfileController::class, 'destroy'])
        ->name('profile.destroy');

    /*
    |--------------------------------------------------------------------------
    | USUARIOS
    |--------------------------------------------------------------------------
    */
    Route::resource('users', \App\Http\Controllers\UserController::class);

    /*
    |--------------------------------------------------------------------------
    | CAMBIO DE CONTRASEÑA OBLIGATORIO
    |--------------------------------------------------------------------------
    */
    Route::get('change-password', [\App\Http\Controllers\Auth\ChangePasswordController::class, 'show'])
        ->name('password.change');

    Route::post('change-password', [\App\Http\Controllers\Auth\ChangePasswordController::class, 'update'])
        ->name('password.change.update');
});

require __DIR__ . '/auth.php';
