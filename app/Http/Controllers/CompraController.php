<?php

namespace App\Http\Controllers;

use App\Models\Compra;
use App\Models\DetalleCompra;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\Proveedor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CompraController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'proveedor_id' => [
                'required',
                'integer',
                Rule::exists('proveedores', 'id')->where('activo', true),
            ],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.producto_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('productos', 'id')->where('activo', true),
            ],
            'detalles.*.cantidad' => ['required', 'integer', 'min:1', 'max:1000000'],
            'detalles.*.precio_unitario' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
        ]);

        $compra = DB::transaction(function () use ($request, $validated) {
            $proveedor = Proveedor::query()
                ->whereKey($validated['proveedor_id'])
                ->where('activo', true)
                ->firstOrFail();
            $productos = Producto::query()
                ->whereKey(collect($validated['detalles'])->pluck('producto_id'))
                ->where('activo', true)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $compra = Compra::create([
                'proveedor_id' => $proveedor->id,
                'usuario_id' => $request->user()->id,
                'proveedor_nombre' => $proveedor->nombre,
                'estado' => 'pendiente',
                'total' => 0,
            ]);
            $total = 0;

            foreach ($validated['detalles'] as $detalle) {
                $producto = $productos->get((int) $detalle['producto_id']);

                if (! $producto) {
                    throw ValidationException::withMessages([
                        'detalles' => 'Uno de los productos ya no está disponible. No se registró la compra.',
                    ]);
                }

                $cantidad = (int) $detalle['cantidad'];
                $precioUnitario = round((float) $detalle['precio_unitario'], 2);
                $subtotal = round($precioUnitario * $cantidad, 2);

                $compra->detalles()->create([
                    'producto_id' => $producto->id,
                    'producto_codigo' => $producto->codigo,
                    'producto_nombre' => $producto->nombre,
                    'cantidad' => $cantidad,
                    'precio_unitario' => $precioUnitario,
                    'subtotal' => $subtotal,
                ]);

                $total += $subtotal;
                if ($total > 9999999999.99) {
                    throw ValidationException::withMessages([
                        'detalles' => 'El total de la compra supera el máximo permitido.',
                    ]);
                }
            }

            $compra->update(['total' => round($total, 2)]);

            return $compra;
        });

        return redirect()->route('inventario.index', ['tab' => 'compras'])
            ->with('success', 'Compra #'.$compra->id.' registrada como pendiente. El stock cambiará al recibirla.');
    }

    public function receive(Request $request, Compra $compra): RedirectResponse
    {
        DB::transaction(function () use ($request, $compra) {
            $lockedPurchase = Compra::query()
                ->whereKey($compra->id)
                ->lockForUpdate()
                ->with('detalles')
                ->firstOrFail();

            if ($lockedPurchase->estado !== 'pendiente') {
                throw ValidationException::withMessages([
                    'compra' => 'Esta compra ya fue recibida y no puede procesarse nuevamente.',
                ]);
            }

            $productIds = $lockedPurchase->detalles->pluck('producto_id');
            if ($productIds->contains(null)) {
                throw ValidationException::withMessages([
                    'compra' => 'No se puede recibir la compra porque uno o más productos fueron eliminados del catálogo.',
                ]);
            }

            $products = Producto::query()
                ->whereKey($productIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($products->count() !== $productIds->unique()->count()) {
                throw ValidationException::withMessages([
                    'compra' => 'No se puede recibir la compra porque uno o más productos ya no están disponibles.',
                ]);
            }

            foreach ($lockedPurchase->detalles as $detalle) {
                $producto = $products->get((int) $detalle->producto_id);
                $stockAnterior = (int) $producto->stock;
                $stockNuevo = $stockAnterior + (int) $detalle->cantidad;

                if ($stockNuevo > 2147483647) {
                    throw ValidationException::withMessages([
                        'compra' => 'La recepción superaría el máximo de stock admitido para '.$detalle->producto_nombre.'.',
                    ]);
                }

                $producto->update([
                    'stock' => $stockNuevo,
                    'precio_compra' => $detalle->precio_unitario,
                ]);

                MovimientoInventario::create([
                    'producto_id' => $producto->id,
                    'producto_codigo' => $producto->codigo,
                    'producto_nombre' => $producto->nombre,
                    'tipo' => 'compra',
                    'stock_anterior' => $stockAnterior,
                    'stock_nuevo' => $stockNuevo,
                    'diferencia' => (int) $detalle->cantidad,
                    'motivo' => 'Compra #'.$lockedPurchase->id.' - '.$lockedPurchase->proveedor_nombre,
                    'usuario_id' => $request->user()->id,
                    'usuario_nombre' => $request->user()->name,
                ]);
            }

            $lockedPurchase->update([
                'estado' => 'recibida',
                'recibida_en' => now(),
            ]);
        });

        return redirect()->route('inventario.index', ['tab' => 'compras'])
            ->with('success', 'Compra #'.$compra->id.' recibida. El stock y el último costo de compra fueron actualizados.');
    }

    public function receipt(Compra $compra): Response
    {
        abort_unless($compra->estado === 'recibida', 404);

        $compra->load('detalles');

        return Inertia::render('Proveedores/Comprobante', [
            'compra' => [
                'id' => $compra->id,
                'proveedor_nombre' => $compra->proveedor_nombre,
                'fecha_registro' => $compra->created_at?->format('d/m/Y H:i'),
                'recibida_en' => $compra->recibida_en?->format('d/m/Y H:i'),
                'total' => (float) $compra->total,
                'detalles' => $compra->detalles->map(fn (DetalleCompra $detalle) => [
                    'producto_codigo' => $detalle->producto_codigo,
                    'producto_nombre' => $detalle->producto_nombre,
                    'cantidad' => $detalle->cantidad,
                    'precio_unitario' => (float) $detalle->precio_unitario,
                    'subtotal' => (float) $detalle->subtotal,
                ])->values(),
            ],
        ]);
    }
}
