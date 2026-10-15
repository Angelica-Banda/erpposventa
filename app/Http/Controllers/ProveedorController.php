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
        $filters = $request->validate([
            'busqueda' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', Rule::in(['pendiente', 'recibida'])],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
        ]);

        $purchaseQuery = Compra::with('detalles');

        if (! empty($filters['busqueda'])) {
            $term = trim($filters['busqueda']);
            $purchaseQuery->where(function (Builder $builder) use ($term) {
                $builder->where('proveedor_nombre', 'like', '%'.$term.'%');
                if (ctype_digit($term)) {
                    $builder->orWhere('id', (int) $term);
                }
            });
        }

        if (! empty($filters['estado'])) {
            $purchaseQuery->where('estado', $filters['estado']);
        }

        if (! empty($filters['desde'])) {
            $purchaseQuery->whereDate('created_at', '>=', $filters['desde']);
        }

        if (! empty($filters['hasta'])) {
            $purchaseQuery->whereDate('created_at', '<=', $filters['hasta']);
        }

        return Inertia::render('Proveedores/Index', [
            'proveedores' => Proveedor::withCount('compras')
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
                ]),
            'proveedoresActivos' => Proveedor::where('activo', true)
                ->orderBy('nombre')
                ->get(['id', 'nombre']),
            'productos' => Producto::where('activo', true)
                ->orderBy('nombre')
                ->get(['id', 'codigo', 'nombre', 'precio_compra'])
                ->map(fn (Producto $producto) => [
                    'id' => $producto->id,
                    'codigo' => $producto->codigo,
                    'nombre' => $producto->nombre,
                    'precio_compra' => (float) $producto->precio_compra,
                ]),
            'compras' => $purchaseQuery
                ->latest()
                ->paginate(15)
                ->withQueryString()
                ->through(fn (Compra $compra) => [
                    'id' => $compra->id,
                    'proveedor_nombre' => $compra->proveedor_nombre,
                    'estado' => $compra->estado,
                    'total' => (float) $compra->total,
                    'created_at' => $compra->created_at?->format('d/m/Y H:i'),
                    'recibida_en' => $compra->recibida_en?->format('d/m/Y H:i'),
                    'detalles' => $compra->detalles->map(fn (DetalleCompra $detalle) => [
                        'producto_codigo' => $detalle->producto_codigo,
                        'producto_nombre' => $detalle->producto_nombre,
                        'cantidad' => $detalle->cantidad,
                        'precio_unitario' => (float) $detalle->precio_unitario,
                        'subtotal' => (float) $detalle->subtotal,
                    ])->values(),
                ]),
            'filters' => [
                'busqueda' => $filters['busqueda'] ?? '',
                'estado' => $filters['estado'] ?? '',
                'desde' => $filters['desde'] ?? '',
                'hasta' => $filters['hasta'] ?? '',
            ],
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
