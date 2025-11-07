<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\FichajesController;

use App\Http\Controllers\ItemController;

Route::get('/', fn() => redirect('/items'));

Route::get('/dashboard', fn() => view('dashboard'))
    ->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    // Items
    Route::get('/items', [ItemController::class, 'index'])->name('items.index');
    Route::post('/items/confirmar', [ItemController::class, 'confirmar'])
        ->middleware('can:validar-vencimientos')
        ->name('items.confirmar');
    Route::get('/items/{codigo}/historial', [ItemController::class, 'historial'])
        ->name('items.historial');
    Route::post('/items/print', [\App\Http\Controllers\ItemController::class, 'imprimirPendientes'])
        ->name('items.imprimir')
        ->middleware('auth');


    // Fichajes (V0)
    Route::get('/fichajes', [FichajesController::class, 'index'])->name('fichajes.index');
    Route::get('/fichajes/detalle/{empleado}', [FichajesController::class, 'detalle'])
        ->name('fichajes.detalle');

    // Perfil
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});


require __DIR__.'/auth.php';
