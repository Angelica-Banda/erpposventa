<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Compra;
use App\Models\DetalleCompra;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\Proveedor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventarioController extends Controller
{
    public function index(Request $request): Response
    {
        $movementFilters = $this->validateMovementFilters($request);

        $products = Producto::with('categoria')
            ->latest()
            ->get()
            ->map(function (Producto $producto) {
                return [
                    'id' => $producto->id,
                    'codigo' => $producto->codigo,
                    'nombre' => $producto->nombre,
                    'descripcion' => $producto->descripcion,
                    'categoria' => $producto->categoria ? [
                        'id' => $producto->categoria->id,
                        'nombre' => $producto->categoria->nombre,
                    ] : null,
                    'precio_compra' => (float) $producto->precio_compra,
                    'precio_venta' => (float) $producto->precio_venta,
                    'stock' => (int) $producto->stock,
                    'stock_minimo' => (int) $producto->stock_minimo,
                    'activo' => (bool) $producto->activo,
                    'created_at' => $producto->created_at?->format('d/m/Y'),
                ];
            });

        $categories = Categoria::select('id', 'nombre', 'descripcion', 'activo')
            ->withCount('productos')
            ->orderBy('nombre')
            ->get();

        $movements = null;
        $compras = null;
        $proveedoresActivos = [];

        if ($request->user()->tieneRol('admin')) {
            $movements = $this->filteredMovementQuery($movementFilters)
                ->latest()
                ->paginate(20, ['*'], 'movements_page')
                ->through(function (MovimientoInventario $movement) {
                    return [
                        'id' => $movement->id,
                        'producto_codigo' => $movement->producto_codigo,
                        'producto_nombre' => $movement->producto_nombre,
                        'tipo' => $movement->tipo,
                        'stock_anterior' => $movement->stock_anterior,
                        'stock_nuevo' => $movement->stock_nuevo,
                        'diferencia' => $movement->diferencia,
                        'motivo' => $movement->motivo,
                        'usuario_nombre' => $movement->usuario_nombre,
                        'created_at' => $movement->created_at?->format('d/m/Y H:i'),
                    ];
                });

            $proveedoresActivos = Proveedor::where('activo', true)
                ->orderBy('nombre')
                ->get(['id', 'nombre']);

            $purchaseQuery = Compra::with('detalles');

            if ($request->filled('compra_busqueda')) {
                $term = trim($request->input('compra_busqueda'));
                $purchaseQuery->where(function (Builder $builder) use ($term) {
                    $builder->where('proveedor_nombre', 'like', '%'.$term.'%');
                    if (ctype_digit($term)) {
                        $builder->orWhere('id', (int) $term);
                    }
                });
            }

            if ($request->filled('compra_estado')) {
                $purchaseQuery->where('estado', $request->input('compra_estado'));
            }

            if ($request->filled('compra_desde')) {
                $purchaseQuery->whereDate('created_at', '>=', $request->input('compra_desde'));
            }

            if ($request->filled('compra_hasta')) {
                $purchaseQuery->whereDate('created_at', '<=', $request->input('compra_hasta'));
            }

            $compras = $purchaseQuery
                ->latest()
                ->paginate(15, ['*'], 'compras_page')
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
                ]);
        }

        return Inertia::render('Inventario/Index', [
            'products' => $products,
            'categories' => $categories,
            'movements' => $movements,
            'movementFilters' => $movementFilters,
            'proveedoresActivos' => $proveedoresActivos,
            'compras' => $compras,
            'comprasFilters' => [
                'busqueda' => $request->input('compra_busqueda', ''),
                'estado' => $request->input('compra_estado', ''),
                'desde' => $request->input('compra_desde', ''),
                'hasta' => $request->input('compra_hasta', ''),
            ],
            'activeTab' => $request->input('tab', 'catalogo'),
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->validateMovementFilters($request);
        $movements = $this->filteredMovementQuery($filters)->orderBy('id')->cursor();

        return response()->streamDownload(function () use ($movements) {
            $output = fopen('php://output', 'w');
            if ($output === false) {
                throw new \RuntimeException('No se pudo iniciar la descarga del CSV.');
            }

            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, [
                'Fecha',
                'Tipo',
                'Código producto',
                'Producto',
                'Stock anterior',
                'Stock nuevo',
                'Diferencia',
                'Motivo',
                'Usuario',
            ], ',', '"', '');

            foreach ($movements as $movement) {
                fputcsv($output, [
                    $movement->created_at?->format('Y-m-d H:i:s') ?? '',
                    match ($movement->tipo) {
                        'inicial' => 'Stock inicial',
                        'ajuste' => 'Ajuste manual',
                        'venta' => 'Venta',
                        'compra' => 'Compra recibida',
                        default => $movement->tipo,
                    },
                    $this->safeCsvText($movement->producto_codigo),
                    $this->safeCsvText($movement->producto_nombre),
                    $movement->stock_anterior,
                    $movement->stock_nuevo,
                    $movement->diferencia,
                    $this->safeCsvText($movement->motivo),
                    $this->safeCsvText($movement->usuario_nombre),
                ], ',', '"', '');
            }

            fclose($output);
        }, 'historial-movimientos-'.now()->format('Ymd_His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportProducts(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $output = fopen('php://output', 'w');
            if ($output === false) {
                throw new \RuntimeException('No se pudo iniciar la descarga del CSV.');
            }

            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, [
                'Código',
                'Producto',
                'Categoría',
                'Precio de compra',
                'Precio de venta',
                'Stock actual',
                'Stock mínimo',
                'Estado',
            ], ',', '"', '');

            Producto::with('categoria')->chunkById(500, function ($products) use ($output) {
                foreach ($products as $product) {
                    fputcsv($output, [
                        $this->safeCsvText($product->codigo),
                        $this->safeCsvText($product->nombre),
                        $this->safeCsvText($product->categoria?->nombre ?? ''),
                        $product->precio_compra,
                        $product->precio_venta,
                        $product->stock,
                        $product->stock_minimo,
                        $product->activo ? 'Activo' : 'Inactivo',
                    ], ',', '"', '');
                }
            });

            fclose($output);
        }, 'catalogo-productos-'.now()->format('Ymd_His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function validateMovementFilters(Request $request): array
    {
        return $request->validate([
            'movement_type' => ['nullable', 'in:inicial,ajuste,venta,compra'],
            'movement_product' => ['nullable', 'string', 'max:150'],
            'movement_from' => ['nullable', 'date_format:Y-m-d'],
            'movement_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:movement_from'],
        ]);
    }

    private function filteredMovementQuery(array $filters): Builder
    {
        $query = MovimientoInventario::query();

        if (! empty($filters['movement_type'])) {
            $query->where('tipo', $filters['movement_type']);
        }

        if (! empty($filters['movement_product'])) {
            $productSearch = '%'.$filters['movement_product'].'%';
            $query->where(function (Builder $query) use ($productSearch) {
                $query->where('producto_nombre', 'like', $productSearch)
                    ->orWhere('producto_codigo', 'like', $productSearch);
            });
        }

        if (! empty($filters['movement_from'])) {
            $query->whereDate('created_at', '>=', $filters['movement_from']);
        }

        if (! empty($filters['movement_to'])) {
            $query->whereDate('created_at', '<=', $filters['movement_to']);
        }

        return $query;
    }

    private function safeCsvText(string $value): string
    {
        return preg_match('/^\s*[=+\-@]/u', $value) === 1
            ? "'".$value
            : $value;
    }
}
