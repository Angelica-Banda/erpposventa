<?php

namespace App\Http\Controllers;

use App\Models\MovimientoInventario;
use App\Models\Producto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MovimientoInventarioController extends Controller
{
    public function store(Request $request, Producto $producto): RedirectResponse
    {
        $validated = $request->validate([
            'stock_nuevo' => ['required', 'integer', 'min:0'],
            'motivo' => ['required', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $producto, $validated) {
            $lockedProduct = Producto::query()
                ->whereKey($producto->id)
                ->lockForUpdate()
                ->firstOrFail();
            $newStock = (int) $validated['stock_nuevo'];
            $oldStock = (int) $lockedProduct->stock;

            $lockedProduct->update(['stock' => $newStock]);

            MovimientoInventario::create([
                'producto_id' => $lockedProduct->id,
                'producto_codigo' => $lockedProduct->codigo,
                'producto_nombre' => $lockedProduct->nombre,
                'tipo' => 'ajuste',
                'stock_anterior' => $oldStock,
                'stock_nuevo' => $newStock,
                'diferencia' => $newStock - $oldStock,
                'motivo' => $validated['motivo'],
                'usuario_id' => $request->user()->id,
                'usuario_nombre' => $request->user()->name,
            ]);
        });

        return redirect()->back()->with('success', 'Ajuste de stock registrado correctamente.');
    }
}
