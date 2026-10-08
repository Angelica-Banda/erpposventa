<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductoController extends Controller
{
    /**
     * List all products with their category.
     */
    public function index()
    {
        return response()->json([
            'data' => Producto::with('categoria')->latest()->get()->map(function (Producto $producto) {
                return [
                    'id' => $producto->id,
                    'categoria_id' => $producto->categoria_id,
                    'categoria' => $producto->categoria ? [
                        'id' => $producto->categoria->id,
                        'nombre' => $producto->categoria->nombre,
                    ] : null,
                    'codigo' => $producto->codigo,
                    'nombre' => $producto->nombre,
                    'descripcion' => $producto->descripcion,
                    'precio_compra' => (float) $producto->precio_compra,
                    'precio_venta' => (float) $producto->precio_venta,
                    'stock' => (int) $producto->stock,
                    'stock_minimo' => (int) $producto->stock_minimo,
                    'activo' => (bool) $producto->activo,
                    'created_at' => $producto->created_at?->toISOString(),
                    'updated_at' => $producto->updated_at?->toISOString(),
                ];
            }),
            'categorias' => Categoria::select('id', 'nombre')->orderBy('nombre')->get(),
        ]);
    }

    /**
     * Store a product.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'categoria_id' => ['required', 'exists:categorias,id'],
            'codigo' => ['required', 'string', 'max:50', 'unique:productos,codigo'],
            'nombre' => ['required', 'string', 'max:150'],
            'descripcion' => ['nullable', 'string'],
            'precio_compra' => ['required', 'numeric', 'min:0'],
            'precio_venta' => ['required', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'stock_minimo' => ['required', 'integer', 'min:0'],
            'activo' => ['sometimes', 'boolean'],
        ]);

        DB::transaction(function () use ($request, $validated) {
            $producto = Producto::create($validated);
            $stockInicial = (int) $producto->stock;

            MovimientoInventario::create([
                'producto_id' => $producto->id,
                'producto_codigo' => $producto->codigo,
                'producto_nombre' => $producto->nombre,
                'tipo' => 'inicial',
                'stock_anterior' => 0,
                'stock_nuevo' => $stockInicial,
                'diferencia' => $stockInicial,
                'motivo' => 'Stock inicial del producto.',
                'usuario_id' => $request->user()->id,
                'usuario_nombre' => $request->user()->name,
            ]);
        });

        return redirect()->back()->with('success', 'Producto creado correctamente.');
    }

    /**
     * Update a product.
     */
    public function update(Request $request, Producto $producto)
    {
        $validated = $request->validate([
            'categoria_id' => ['required', 'exists:categorias,id'],
            'codigo' => ['required', 'string', 'max:50', 'unique:productos,codigo,'.$producto->id],
            'nombre' => ['required', 'string', 'max:150'],
            'descripcion' => ['nullable', 'string'],
            'precio_compra' => ['required', 'numeric', 'min:0'],
            'precio_venta' => ['required', 'numeric', 'min:0'],
            'stock_minimo' => ['required', 'integer', 'min:0'],
            'activo' => ['sometimes', 'boolean'],
        ]);

        $producto->update($validated);

        return redirect()->back()->with('success', 'Producto actualizado correctamente.');
    }

    /**
     * Delete a product.
     */
    public function destroy(Producto $producto)
    {
        $producto->delete();

        return redirect()->back()->with('success', 'Producto eliminado correctamente.');
    }
}
