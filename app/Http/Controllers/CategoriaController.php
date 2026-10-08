<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Producto;
use Illuminate\Http\Request;

class CategoriaController extends Controller
{
    /**
     * List all categories.
     */
    public function index()
    {
        return response()->json([
            'data' => Categoria::withCount('productos')->latest()->get()->map(function (Categoria $categoria) {
                return [
                    'id' => $categoria->id,
                    'nombre' => $categoria->nombre,
                    'descripcion' => $categoria->descripcion,
                    'activo' => (bool) $categoria->activo,
                    'productos_count' => $categoria->productos_count,
                    'created_at' => $categoria->created_at?->toISOString(),
                    'updated_at' => $categoria->updated_at?->toISOString(),
                ];
            }),
        ]);
    }

    /**
     * Store a category.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:150', 'unique:categorias,nombre'],
            'descripcion' => ['nullable', 'string'],
            'activo' => ['sometimes', 'boolean'],
        ]);

        Categoria::create($validated);

        return redirect()->back()->with('success', 'Categoría creada correctamente.');
    }

    /**
     * Update a category.
     */
    public function update(Request $request, Categoria $categoria)
    {
        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:150', 'unique:categorias,nombre,'.$categoria->id],
            'descripcion' => ['nullable', 'string'],
            'activo' => ['sometimes', 'boolean'],
        ]);

        $categoria->update($validated);

        return redirect()->back()->with('success', 'Categoría actualizada correctamente.');
    }

    /**
     * Delete a category when it has no products assigned.
     */
    public function destroy(Categoria $categoria)
    {
        if (Producto::where('categoria_id', $categoria->id)->exists()) {
            return redirect()->back()->with('error', 'No se puede eliminar la categoría porque tiene productos asociados.');
        }

        $categoria->delete();

        return redirect()->back()->with('success', 'Categoría eliminada correctamente.');
    }
}
