<?php

namespace App\Http\Controllers;

use App\Models\Caja;
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
    public function index(Request $request): Response
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
            'cajaAbierta' => Caja::query()
                ->where('usuario_id', $request->user()->id)
                ->where('estado', 'abierta')
                ->exists(),
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
            'tipo_venta' => ['sometimes', 'required', 'in:mostrador,domicilio'],
            'cliente_nombre' => ['required_if:tipo_venta,domicilio', 'nullable', 'string', 'max:255'],
            'cliente_telefono' => ['required_if:tipo_venta,domicilio', 'nullable', 'string', 'max:30'],
            'domicilio_direccion' => ['required_if:tipo_venta,domicilio', 'nullable', 'string', 'max:2000'],
            'costo_domicilio' => ['required_if:tipo_venta,domicilio', 'nullable', 'numeric', 'min:0', 'max:9999999999.99'],
        ]);

        $quantities = collect($validated['items'])->keyBy('product_id');
        $tipoVenta = $validated['tipo_venta'] ?? 'mostrador';

        $sale = DB::transaction(function () use ($request, $quantities, $validated, $tipoVenta) {
            $caja = Caja::query()
                ->where('usuario_id', $request->user()->id)
                ->where('estado', 'abierta')
                ->lockForUpdate()
                ->first();

            if (! $caja) {
                throw ValidationException::withMessages([
                    'caja' => 'Debes abrir tu caja antes de registrar una venta.',
                ]);
            }

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
                'caja_id' => $caja->id,
                'vendedor_nombre' => $request->user()->name,
                'total' => 0,
                'tipo_venta' => $tipoVenta,
                'cliente_nombre' => $tipoVenta === 'domicilio' ? $validated['cliente_nombre'] : null,
                'cliente_telefono' => $tipoVenta === 'domicilio' ? $validated['cliente_telefono'] : null,
                'domicilio_direccion' => $tipoVenta === 'domicilio' ? $validated['domicilio_direccion'] : null,
                'costo_domicilio' => $tipoVenta === 'domicilio' ? round((float) $validated['costo_domicilio'], 2) : 0,
                'domicilio_estado' => $tipoVenta === 'domicilio' ? 'pendiente' : null,
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

            $total += $tipoVenta === 'domicilio' ? (float) $validated['costo_domicilio'] : 0;
            $sale->update(['total' => round($total, 2)]);

            return $sale;
        });

        $sale->load('detalles');

        $receipt = [
            'id' => $sale->id,
            'date' => $sale->created_at->format('d/m/Y H:i'),
            'seller' => $sale->vendedor_nombre,
            'tipo_venta' => $sale->tipo_venta,
            'cliente_nombre' => $sale->cliente_nombre,
            'cliente_telefono' => $sale->cliente_telefono,
            'domicilio_direccion' => $sale->domicilio_direccion,
            'costo_domicilio' => (float) $sale->costo_domicilio,
            'domicilio_estado' => $sale->domicilio_estado,
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
