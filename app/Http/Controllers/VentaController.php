<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use App\Models\MovimientoInventario;
use App\Models\Venta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class VentaController extends Controller
{
    public function index(): Response
    {
        $products = Producto::with('categoria')
            ->where('activo', true)
            ->orderBy('nombre')
            ->get()
            ->map(function (Producto $producto) {
                return [
                    'id' => $producto->id,
                    'name' => $producto->nombre,
                    'price' => (float) $producto->precio_venta,
                    'category' => $producto->categoria?->nombre ?? 'Sin categoría',
                    'stock' => (int) $producto->stock,
                ];
            });

        return Inertia::render('Ventas/Index', [
            'products' => $products,
            'receipt' => session('receipt'),
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:productos,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $quantities = collect($validated['items'])->keyBy('product_id');

        $sale = DB::transaction(function () use ($request, $quantities) {
            $products = Producto::query()
                ->whereKey($quantities->keys())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $unavailableProducts = [];

            foreach ($quantities as $productId => $item) {
                $product = $products->get((int) $productId);
                $quantity = (int) $item['quantity'];

                if (! $product || ! $product->activo || $product->stock < $quantity) {
                    $unavailableProducts[] = $product?->nombre ?? 'Producto no disponible';
                }
            }

            if ($unavailableProducts !== []) {
                throw ValidationException::withMessages([
                    'items' => 'No hay stock suficiente para: '.implode(', ', $unavailableProducts).'. No se registró la venta.',
                ]);
            }

            $sale = Venta::create([
                'usuario_id' => $request->user()->id,
                'vendedor_nombre' => $request->user()->name,
                'total' => 0,
            ]);
            $total = 0;

            foreach ($quantities as $productId => $item) {
                $product = $products->get((int) $productId);
                $quantity = (int) $item['quantity'];
                $unitPrice = (float) $product->precio_venta;
                $subtotal = round($unitPrice * $quantity, 2);
                $oldStock = (int) $product->stock;
                $newStock = $oldStock - $quantity;

                $sale->detalles()->create([
                    'producto_id' => $product->id,
                    'producto_codigo' => $product->codigo,
                    'producto_nombre' => $product->nombre,
                    'cantidad' => $quantity,
                    'precio_unitario' => $unitPrice,
                    'subtotal' => $subtotal,
                ]);

                $product->update(['stock' => $newStock]);

                MovimientoInventario::create([
                    'producto_id' => $product->id,
                    'producto_codigo' => $product->codigo,
                    'producto_nombre' => $product->nombre,
                    'tipo' => 'venta',
                    'stock_anterior' => $oldStock,
                    'stock_nuevo' => $newStock,
                    'diferencia' => -$quantity,
                    'motivo' => 'Venta #'.$sale->id,
                    'usuario_id' => $request->user()->id,
                    'usuario_nombre' => $request->user()->name,
                ]);

                $total += $subtotal;
            }

            $sale->update(['total' => round($total, 2)]);

            return $sale;
        });

        $sale->load('detalles');

        $receipt = [
            'id' => $sale->id,
            'date' => $sale->created_at->format('d/m/Y H:i'),
            'seller' => $sale->vendedor_nombre,
            'total' => (float) $sale->total,
            'items' => $sale->detalles->map(fn ($detail) => [
                'code' => $detail->producto_codigo,
                'name' => $detail->producto_nombre,
                'quantity' => $detail->cantidad,
                'unit_price' => (float) $detail->precio_unitario,
                'subtotal' => (float) $detail->subtotal,
            ])->values()->all(),
        ];

        return redirect()->route('ventas.index')
            ->with('success', 'Venta #'.$sale->id.' registrada correctamente y stock actualizado.')
            ->with('receipt', $receipt);
    }
}
