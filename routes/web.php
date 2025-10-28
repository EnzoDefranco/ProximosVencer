<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ItemController;

Route::get('/', function () {
    return view('welcome');
});

// Opcional: si querés que el inicio vaya directo a la grilla
// Route::redirect('/', '/items');

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    // Próximos Vencimientos (ERP read + checks)
    Route::get('/items', [ItemController::class, 'index'])->name('items.index');

    // Solo pueden confirmar admin/deposito (Gate 'validar-vencimientos')
    Route::post('/items/confirmar', [ItemController::class, 'confirmar'])
        ->middleware('can:validar-vencimientos')
        ->name('items.confirmar');

    // Perfil (Breeze)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
