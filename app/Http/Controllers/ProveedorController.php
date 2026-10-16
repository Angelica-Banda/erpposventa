<?php

namespace App\Http\Controllers;

use App\Models\Compra;
use App\Models\DetalleCompra;
use App\Models\Producto;
use App\Models\Proveedor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProveedorController extends Controller
{
    public function index(Request $request): Response
    {
        $proveedores = Proveedor::withCount('compras')
            ->orderBy('nombre')
            ->get()
            ->map(fn (Proveedor $proveedor) => [
                'id' => $proveedor->id,
                'nombre' => $proveedor->nombre,
                'nit' => $proveedor->nit,
                'telefono' => $proveedor->telefono,
                'correo' => $proveedor->correo,
                'direccion' => $proveedor->direccion,
                'activo' => (bool) $proveedor->activo,
                'compras_count' => $proveedor->compras_count,
            ]);

        return Inertia::render('Proveedores/Index', [
            'proveedores' => $proveedores,
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'nit' => ['nullable', 'string', 'max:30', 'unique:proveedores,nit'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'correo' => ['nullable', 'email', 'max:255'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'activo' => ['sometimes', 'boolean'],
        ]);

        Proveedor::create($validated);

        return redirect()->back()->with('success', 'Proveedor creado correctamente.');
    }

    public function update(Request $request, Proveedor $proveedor): RedirectResponse
    {
        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'nit' => ['nullable', 'string', 'max:30', Rule::unique('proveedores', 'nit')->ignore($proveedor->id)],
            'telefono' => ['nullable', 'string', 'max:30'],
            'correo' => ['nullable', 'email', 'max:255'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'activo' => ['required', 'boolean'],
        ]);

        $proveedor->update($validated);

        return redirect()->back()->with('success', 'Proveedor actualizado correctamente.');
    }

    public function destroy(Proveedor $proveedor): RedirectResponse
    {
        if ($proveedor->compras()->exists()) {
            return redirect()->back()->with('error', 'No se puede eliminar un proveedor que tiene compras registradas. Puedes marcarlo como inactivo.');
        }

        $proveedor->delete();

        return redirect()->back()->with('success', 'Proveedor eliminado correctamente.');
    }
}
