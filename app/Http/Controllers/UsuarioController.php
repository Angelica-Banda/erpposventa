<?php

namespace App\Http\Controllers;

use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UsuarioController extends Controller
{
    /**
     * Muestra los usuarios con sus roles asignados.
     */
    public function index(): Response
    {
        $usuarios = Usuario::with('roles')->latest()->get()->map(function ($usuario) {
            return [
                'id' => $usuario->id,
                'name' => $usuario->name,
                'email' => $usuario->email,
                'created_at' => $usuario->created_at ? $usuario->created_at->format('d/m/Y') : '',
                'roles' => $usuario->roles->map(fn (Rol $rol) => [
                    'id' => $rol->id,
                    'nombre' => $rol->nombre,
                    'nombre_visible' => $rol->nombre_visible,
                ]),
                'ids_roles' => $usuario->roles->pluck('id')->toArray(),
            ];
        });

        $roles = Rol::all(['id', 'nombre', 'nombre_visible', 'descripcion']);

        return Inertia::render('Configuracion/UsuariosRoles', [
            'usuarios' => $usuarios,
            'roles' => $roles,
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ],
        ]);
    }

    /**
     * Registra un usuario y le asigna sus roles.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:usuarios,email',
            'password' => 'required|string|min:6',
            'roles' => 'required|array|min:1',
            'roles.*' => 'exists:roles,id',
        ], [
            'roles.required' => 'Debes asignar al menos un rol al usuario.',
            'roles.min' => 'Debes asignar al menos un rol al usuario.',
        ]);

        $usuario = Usuario::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        $usuario->roles()->sync($validated['roles']);

        return redirect()->back()->with('success', 'Usuario creado y roles asignados exitosamente.');
    }

    /**
     * Actualiza los datos del usuario y sincroniza sus roles.
     */
    public function update(Request $request, Usuario $usuario)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('usuarios')->ignore($usuario->id)],
            'password' => 'nullable|string|min:6',
            'roles' => 'required|array|min:1',
            'roles.*' => 'exists:roles,id',
        ], [
            'roles.required' => 'Debes asignar al menos un rol al usuario.',
            'roles.min' => 'Debes asignar al menos un rol al usuario.',
        ]);

        $datosUsuario = [
            'name' => $validated['name'],
            'email' => $validated['email'],
        ];

        if (! empty($validated['password'])) {
            $datosUsuario['password'] = Hash::make($validated['password']);
        }

        $usuario->update($datosUsuario);
        $usuario->roles()->sync($validated['roles']);

        return redirect()->back()->with('success', 'Usuario y roles actualizados correctamente.');
    }

    /**
     * Elimina un usuario del sistema.
     */
    public function destroy(Usuario $usuario, Request $request)
    {
        if ($request->user()->id === $usuario->id) {
            return redirect()->back()->with('error', 'No puedes eliminar tu propio usuario de sesión.');
        }

        $usuario->roles()->detach();
        $usuario->delete();

        return redirect()->back()->with('success', 'Usuario eliminado correctamente.');
    }
}
