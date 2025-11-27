<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Gate;

class UserController extends Controller
{
    public function index()
    {
        abort_unless(Gate::allows('eliminar-vencidos'), 403); // Solo admin

        $users = User::orderBy('name')->get();

        return view('users.index', compact('users'));
    }

    public function create()
    {
        abort_unless(Gate::allows('eliminar-vencidos'), 403);

        return view('users.create');
    }

    public function store(Request $request)
    {
        abort_unless(Gate::allows('eliminar-vencidos'), 403);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'role' => 'nullable|string|in:admin,deposito,user', // Ajusta roles según necesites
        ]);

        $password = Str::random(8); // Contraseña aleatoria de 8 caracteres

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($password),
            'role' => $request->role,
            'must_change_password' => true,
        ]);

        return redirect()->route('users.index')
            ->with('ok', 'Usuario creado correctamente.')
            ->with('generated_password', $password); // Pasamos la contraseña para mostrarla
    }

    public function destroy(User $user)
    {
        abort_unless(Gate::allows('eliminar-vencidos'), 403);

        if ($user->id === auth()->id()) {
            return back()->with('error', 'No puedes eliminarte a ti mismo.');
        }

        $user->delete();

        return back()->with('ok', 'Usuario eliminado.');
    }
}
