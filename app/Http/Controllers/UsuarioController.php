<?php

namespace App\Http\Controllers;

use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UsuarioController extends Controller
{
    /**
     * Muestra los usuarios con sus roles asignados.
     */
    public function index(Request $request): Response
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
            'currentUserId' => $request->user()->id,
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
            'roles.*' => ['required', 'integer', 'distinct', 'exists:roles,id'],
        ], [
            'roles.required' => 'Debes asignar al menos un rol al usuario.',
            'roles.min' => 'Debes asignar al menos un rol al usuario.',
        ]);

        DB::transaction(function () use ($validated) {
            $usuario = Usuario::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
            ]);

            $usuario->roles()->sync($validated['roles']);
        });

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
            'roles.*' => ['required', 'integer', 'distinct', 'exists:roles,id'],
        ], [
            'roles.required' => 'Debes asignar al menos un rol al usuario.',
            'roles.min' => 'Debes asignar al menos un rol al usuario.',
        ]);

        DB::transaction(function () use ($request, $usuario, $validated) {
            $administradores = $this->bloquearAdministradoresActivos();
            $usuarioBloqueado = Usuario::query()->lockForUpdate()->findOrFail($usuario->id);
            $rolesActuales = $usuarioBloqueado->roles()->pluck('roles.id')->map(fn ($id) => (int) $id)->sort()->values()->all();
            $rolesNuevos = collect($validated['roles'])->map(fn ($id) => (int) $id)->sort()->values()->all();

            if ((int) $request->user()->id === (int) $usuarioBloqueado->id && $rolesActuales !== $rolesNuevos) {
                throw ValidationException::withMessages([
                    'roles' => 'No puedes cambiar los roles de tu propio usuario.',
                ]);
            }

            $rolAdministradorId = Rol::query()->where('nombre', 'admin')->value('id');
            $esAdministrador = $rolAdministradorId !== null && in_array((int) $rolAdministradorId, $rolesActuales, true);
            $conservaAdministrador = $rolAdministradorId !== null && in_array((int) $rolAdministradorId, $rolesNuevos, true);

            if ($esAdministrador && ! $conservaAdministrador && $administradores->count() <= 1) {
                throw ValidationException::withMessages([
                    'roles' => 'No se puede retirar el último rol de Administrador activo del sistema.',
                ]);
            }

            $datosUsuario = [
                'name' => $validated['name'],
                'email' => $validated['email'],
            ];

            if (! empty($validated['password'])) {
                $datosUsuario['password'] = Hash::make($validated['password']);
            }

            $usuarioBloqueado->update($datosUsuario);
            $usuarioBloqueado->roles()->sync($rolesNuevos);
        });

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

        DB::transaction(function () use ($usuario) {
            $administradores = $this->bloquearAdministradoresActivos();
            $usuarioBloqueado = Usuario::query()->lockForUpdate()->findOrFail($usuario->id);
            $rolAdministradorId = Rol::query()->where('nombre', 'admin')->value('id');
            $esAdministrador = $rolAdministradorId !== null
                && $usuarioBloqueado->roles()->where('roles.id', $rolAdministradorId)->exists();

            if ($esAdministrador && $administradores->count() <= 1) {
                throw ValidationException::withMessages([
                    'usuario' => 'No se puede eliminar el último usuario Administrador activo del sistema.',
                ]);
            }

            $usuarioBloqueado->roles()->detach();
            $usuarioBloqueado->delete();
        });

        return redirect()->back()->with('success', 'Usuario eliminado correctamente.');
    }

    private function bloquearAdministradoresActivos(): \Illuminate\Support\Collection
    {
        return Usuario::query()
            ->whereHas('roles', fn (Builder $query) => $query->where('roles.nombre', 'admin'))
            ->orderBy('usuarios.id')
            ->lockForUpdate()
            ->get(['usuarios.id']);
    }
}
