<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Caja;
use App\Models\Compra;
use App\Models\DetalleCompra;
use App\Models\DetalleVenta;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\Venta;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use ZipArchive;

class ReporteController extends Controller
{
    private const REPORTES = ['ventas', 'inventario', 'compras', 'caja', 'domicilios'];

    public function index(Request $request): Response
    {
        $inicioMes = now()->startOfMonth()->toDateString();
        $finMes = now()->endOfMonth()->toDateString();

        $filtros = $request->validate([
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'reporte' => ['nullable', Rule::in(self::REPORTES)],
        ]);

        $desde = $filtros['desde'] ?? $inicioMes;
        $hasta = $filtros['hasta'] ?? ($filtros['desde'] ?? $finMes);
        $reporte = $filtros['reporte'] ?? 'ventas';
        $fechaInicio = $desde.' 00:00:00';
        $fechaFin = $hasta.' 23:59:59.999999';

        [$resumen, $registros] = match ($reporte) {
            'inventario' => $this->reporteInventario($fechaInicio, $fechaFin, true),
            'compras' => $this->reporteCompras($fechaInicio, $fechaFin, true),
            'caja' => $this->reporteCaja($fechaInicio, $fechaFin, true),
            'domicilios' => $this->reporteDomicilios($fechaInicio, $fechaFin, true),
            default => $this->reporteVentas($fechaInicio, $fechaFin, true),
        };

        return Inertia::render('Reportes/Index', [
            'reporteActual' => $reporte,
            'filtros' => [
                'desde' => $desde,
                'hasta' => $hasta,
            ],
            'resumen' => $resumen,
            'registros' => $registros,
            'analisis' => $this->generarAnalisis($reporte, $fechaInicio, $fechaFin, $desde, $hasta, $resumen),
        ]);
    }

    public function export(Request $request, string $formato)
    {
        abort_unless(in_array($formato, ['pdf', 'excel'], true), 404);

        $filtros = $request->validate([
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'reporte' => ['nullable', Rule::in(self::REPORTES)],
        ]);

        $desde = $filtros['desde'] ?? now()->startOfMonth()->toDateString();
        $hasta = $filtros['hasta'] ?? ($filtros['desde'] ?? now()->endOfMonth()->toDateString());
        $reporte = $filtros['reporte'] ?? 'ventas';
        $inicio = $desde.' 00:00:00';
        $fin = $hasta.' 23:59:59.999999';

        [$resumen, $registros] = match ($reporte) {
            'inventario' => $this->reporteInventario($inicio, $fin, false),
            'compras' => $this->reporteCompras($inicio, $fin, false),
            'caja' => $this->reporteCaja($inicio, $fin, false),
            'domicilios' => $this->reporteDomicilios($inicio, $fin, false),
            default => $this->reporteVentas($inicio, $fin, false),
        };

        $nombreReporte = ucfirst($reporte);
        $nombreArchivo = 'reporte-'.$reporte.'-'.$desde.'-'.$hasta;
        $columnas = $this->columnas($reporte);
        $filas = $registros->all();
        $analisis = $this->generarAnalisis($reporte, $inicio, $fin, $desde, $hasta, $resumen);

        if ($formato === 'pdf') {
            return Pdf::loadView('reportes.pdf', [
                'titulo' => 'Reporte de '.$nombreReporte,
                'reporte' => $reporte,
                'desde' => $desde,
                'hasta' => $hasta,
                'resumen' => $resumen,
                'columnas' => $columnas,
                'filas' => $filas,
                'analisis' => $analisis,
            ])
                ->setPaper('a4', 'landscape')
                ->download($nombreArchivo.'.pdf');
        }

        return $this->descargarExcel($nombreArchivo, $nombreReporte, $desde, $hasta, $resumen, $columnas, $filas, $analisis);
    }

    private function reporteVentas(string $inicio, string $fin, bool $paginar): array
    {
        $consulta = Venta::query()->whereBetween('created_at', [$inicio, $fin]);
        $resumen = (clone $consulta)
            ->selectRaw('COUNT(*) as cantidad, COALESCE(SUM(total), 0) as total, COALESCE(SUM(costo_domicilio), 0) as domicilios')
            ->first();

        $registros = $this->mapearRegistros($consulta->latest(), $paginar, fn (Venta $venta) => [
            'id' => $venta->id,
            'fecha' => $venta->created_at?->format('d/m/Y H:i'),
            'usuario' => $venta->vendedor_nombre,
            'tipo' => $venta->tipo_venta === 'domicilio' ? 'Domicilio' : 'Mostrador',
            'estado' => $venta->domicilio_estado,
            'costo_adicional' => (float) $venta->costo_domicilio,
            'total' => (float) $venta->total,
        ]);

        return [[
            ['label' => 'Ventas registradas', 'value' => (int) $resumen->cantidad, 'format' => 'number'],
            ['label' => 'Total vendido', 'value' => (float) $resumen->total, 'format' => 'currency'],
            ['label' => 'Costos de domicilios', 'value' => (float) $resumen->domicilios, 'format' => 'currency'],
        ], $registros];
    }

    private function reporteInventario(string $inicio, string $fin, bool $paginar): array
    {
        $consulta = MovimientoInventario::query()->whereBetween('created_at', [$inicio, $fin]);
        $resumen = (clone $consulta)
            ->selectRaw('COUNT(*) as cantidad, COALESCE(SUM(CASE WHEN diferencia > 0 THEN diferencia ELSE 0 END), 0) as entradas, COALESCE(SUM(CASE WHEN diferencia < 0 THEN ABS(diferencia) ELSE 0 END), 0) as salidas')
            ->first();

        $productosBajoStock = Producto::query()
            ->where('activo', true)
            ->whereColumn('stock', '<=', 'stock_minimo')
            ->count();

        $registros = $this->mapearRegistros($consulta->latest(), $paginar, fn (MovimientoInventario $movimiento) => [
            'id' => $movimiento->id,
            'fecha' => $movimiento->created_at?->format('d/m/Y H:i'),
            'producto' => $movimiento->producto_nombre,
            'codigo' => $movimiento->producto_codigo,
            'tipo' => ucfirst($movimiento->tipo),
            'diferencia' => (int) $movimiento->diferencia,
            'stock_anterior' => (int) $movimiento->stock_anterior,
            'stock_nuevo' => (int) $movimiento->stock_nuevo,
            'motivo' => $movimiento->motivo,
            'usuario' => $movimiento->usuario_nombre,
        ]);

        return [[
            ['label' => 'Movimientos', 'value' => (int) $resumen->cantidad, 'format' => 'number'],
            ['label' => 'Unidades ingresadas', 'value' => (int) $resumen->entradas, 'format' => 'number'],
            ['label' => 'Unidades salidas', 'value' => (int) $resumen->salidas, 'format' => 'number'],
            ['label' => 'Productos en stock mínimo', 'value' => $productosBajoStock, 'format' => 'number'],
        ], $registros];
    }

    private function reporteCompras(string $inicio, string $fin, bool $paginar): array
    {
        $consulta = Compra::query()->whereBetween('created_at', [$inicio, $fin]);
        $resumen = (clone $consulta)
            ->selectRaw("COUNT(*) as cantidad, COALESCE(SUM(total), 0) as total, SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes, SUM(CASE WHEN estado = 'recibida' THEN 1 ELSE 0 END) as recibidas")
            ->first();

        $registros = $this->mapearRegistros($consulta->latest(), $paginar, fn (Compra $compra) => [
            'id' => $compra->id,
            'fecha' => $compra->created_at?->format('d/m/Y H:i'),
            'proveedor' => $compra->proveedor_nombre,
            'estado' => ucfirst($compra->estado),
            'total' => (float) $compra->total,
            'recibida_en' => $compra->recibida_en?->format('d/m/Y H:i'),
        ]);

        return [[
            ['label' => 'Compras registradas', 'value' => (int) $resumen->cantidad, 'format' => 'number'],
            ['label' => 'Valor de compras', 'value' => (float) $resumen->total, 'format' => 'currency'],
            ['label' => 'Pendientes de recibir', 'value' => (int) $resumen->pendientes, 'format' => 'number'],
            ['label' => 'Compras recibidas', 'value' => (int) $resumen->recibidas, 'format' => 'number'],
        ], $registros];
    }

    private function reporteCaja(string $inicio, string $fin, bool $paginar): array
    {
        $consulta = Caja::query()
            ->where('estado', 'cerrada')
            ->whereBetween('cerrada_en', [$inicio, $fin]);
        $resumen = (clone $consulta)
            ->selectRaw('COUNT(*) as cantidad, COALESCE(SUM(monto_esperado), 0) as esperado, COALESCE(SUM(monto_contado), 0) as contado, COALESCE(SUM(diferencia), 0) as diferencia')
            ->first();

        $registros = $this->mapearRegistros($consulta->latest('cerrada_en'), $paginar, fn (Caja $caja) => [
            'id' => $caja->id,
            'fecha' => $caja->cerrada_en?->format('d/m/Y H:i'),
            'usuario' => $caja->usuario_nombre,
            'monto_inicial' => (float) $caja->monto_inicial,
            'monto_esperado' => (float) $caja->monto_esperado,
            'monto_contado' => (float) $caja->monto_contado,
            'diferencia' => (float) $caja->diferencia,
        ]);

        return [[
            ['label' => 'Cajas cerradas', 'value' => (int) $resumen->cantidad, 'format' => 'number'],
            ['label' => 'Efectivo esperado', 'value' => (float) $resumen->esperado, 'format' => 'currency'],
            ['label' => 'Efectivo contado', 'value' => (float) $resumen->contado, 'format' => 'currency'],
            ['label' => 'Diferencia acumulada', 'value' => (float) $resumen->diferencia, 'format' => 'currency'],
        ], $registros];
    }

    private function reporteDomicilios(string $inicio, string $fin, bool $paginar): array
    {
        $consulta = Venta::query()
            ->where('tipo_venta', 'domicilio')
            ->whereBetween('created_at', [$inicio, $fin]);
        $resumen = (clone $consulta)
            ->selectRaw("COUNT(*) as cantidad, COALESCE(SUM(total), 0) as total, COALESCE(SUM(costo_domicilio), 0) as costos, SUM(CASE WHEN domicilio_estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes, SUM(CASE WHEN domicilio_estado = 'entregado' THEN 1 ELSE 0 END) as entregados")
            ->first();

        $registros = $this->mapearRegistros($consulta->latest(), $paginar, fn (Venta $venta) => [
            'id' => $venta->id,
            'fecha' => $venta->created_at?->format('d/m/Y H:i'),
            'cliente' => $venta->cliente_nombre,
            'telefono' => $venta->cliente_telefono,
            'direccion' => $venta->domicilio_direccion,
            'estado' => ucfirst((string) $venta->domicilio_estado),
            'costo_domicilio' => (float) $venta->costo_domicilio,
            'total' => (float) $venta->total,
            'usuario' => $venta->vendedor_nombre,
        ]);

        return [[
            ['label' => 'Domicilios', 'value' => (int) $resumen->cantidad, 'format' => 'number'],
            ['label' => 'Ventas de domicilios', 'value' => (float) $resumen->total, 'format' => 'currency'],
            ['label' => 'Costos cobrados', 'value' => (float) $resumen->costos, 'format' => 'currency'],
            ['label' => 'Pendientes / entregados', 'value' => (int) $resumen->pendientes.' / '.(int) $resumen->entregados, 'format' => 'text'],
        ], $registros];
    }

    private function generarAnalisis(string $reporte, string $inicio, string $fin, string $desde, string $hasta, array $resumen): array
    {
        $comparacion = $this->comparacionPeriodo($reporte, $inicio, $fin);
        $graficos = [];
        $hallazgos = [];
        $recomendaciones = [];
        $detalleProductos = [];
        $total = (float) data_get($resumen, '1.value', 0);
        $cantidad = (int) data_get($resumen, '0.value', 0);
        $resumenEjecutivo = '';

        if ($reporte === 'ventas') {
            $ventas = Venta::query()->whereBetween('created_at', [$inicio, $fin])->get(['created_at', 'total', 'tipo_venta']);
            $productosVendidos = DetalleVenta::query()
                ->join('ventas', 'ventas.id', '=', 'detalle_ventas.venta_id')
                ->whereBetween('ventas.created_at', [$inicio, $fin])
                ->selectRaw('detalle_ventas.producto_nombre as nombre, SUM(detalle_ventas.cantidad) as cantidad')
                ->groupBy('detalle_ventas.producto_nombre')
                ->orderByDesc('cantidad')
                ->get();

            $graficos[] = $this->graficoTemporal('Evolución de ventas', $ventas, 'total', 'currency');
            if ($productosVendidos->isNotEmpty()) {
                $seleccion = $productosVendidos->take(5)->values();
                $graficos[] = $this->graficoUnico(
                    'Productos más vendidos (unidades)',
                    $seleccion->pluck('nombre')->all(),
                    $seleccion->pluck('cantidad')->map(fn ($value) => (int) $value)->all(),
                    'number',
                );
                $nombresMasVendidos = $seleccion->pluck('nombre')->all();
                $menosVendidos = $productosVendidos
                    ->sortBy('cantidad')
                    ->reject(fn ($producto) => in_array($producto->nombre, $nombresMasVendidos, true))
                    ->take(5)
                    ->values();
                if ($menosVendidos->isNotEmpty()) {
                    $graficos[] = $this->graficoUnico(
                        'Productos con menor movimiento entre los vendidos (unidades)',
                        $menosVendidos->pluck('nombre')->all(),
                        $menosVendidos->pluck('cantidad')->map(fn ($value) => (int) $value)->all(),
                        'number',
                    );
                }
                $hallazgos[] = sprintf('El producto con más unidades vendidas fue %s (%d unidades).', $productosVendidos->first()->nombre, (int) $productosVendidos->first()->cantidad);
            } else {
                $hallazgos[] = 'No hay detalle de productos vendido suficiente para comparar su movimiento.';
            }

            $ventaMostrador = $ventas->filter(fn (Venta $venta) => $venta->tipo_venta !== 'domicilio')->count();
            $ventaDomicilio = $ventas->count() - $ventaMostrador;
            if ($ventas->isNotEmpty()) {
                $graficos[] = $this->graficoUnico('Ventas por tipo', ['Mostrador', 'Domicilio'], [$ventaMostrador, $ventaDomicilio], 'number');
            }
            $resumenEjecutivo = sprintf('%d ventas por %s; promedio por venta: %s.', $cantidad, $this->formatearMoneda($total), $this->formatearMoneda($cantidad ? $total / $cantidad : 0));
            $hallazgos[] = sprintf('Se registraron %d ventas de mostrador y %d a domicilio.', $ventaMostrador, $ventaDomicilio);
            $recomendaciones[] = $this->recomendacionComparacion('Las ventas', $comparacion);
            $recomendaciones[] = 'Revisa la disponibilidad de los productos con mayor movimiento y evalúa promociones según la demanda observada.';
        } elseif ($reporte === 'inventario') {
            $productosBajos = Producto::query()->where('activo', true)->whereColumn('stock', '<=', 'stock_minimo')->orderBy('stock')->limit(8)->get(['nombre', 'stock', 'stock_minimo']);
            $ventasPorProducto = DetalleVenta::query()
                ->join('ventas', 'ventas.id', '=', 'detalle_ventas.venta_id')
                ->whereBetween('ventas.created_at', [$inicio, $fin])
                ->selectRaw('detalle_ventas.producto_nombre as nombre, SUM(detalle_ventas.cantidad) as cantidad')
                ->groupBy('detalle_ventas.producto_nombre')
                ->orderBy('cantidad')
                ->get()
                ->keyBy('nombre');
            $productosActivos = Producto::query()->where('activo', true)->get(['nombre', 'stock']);
            $valorInventario = (float) Producto::query()->where('activo', true)->selectRaw('COALESCE(SUM(stock * precio_compra), 0) as valor')->value('valor');
            $productosConExistencias = $productosActivos->filter(fn (Producto $producto) => $producto->stock > 0)->count();
            $productosAgotados = $productosActivos->count() - $productosConExistencias;

            if ($productosBajos->isNotEmpty()) {
                $graficos[] = $this->graficoSeries(
                    'Stock actual bajo o mínimo (stock actual y mínimo configurado)',
                    $productosBajos->pluck('nombre')->all(),
                    [
                        ['nombre' => 'Stock actual', 'valores' => $productosBajos->pluck('stock')->map(fn ($value) => (int) $value)->all()],
                        ['nombre' => 'Stock mínimo', 'valores' => $productosBajos->pluck('stock_minimo')->map(fn ($value) => (int) $value)->all()],
                    ],
                    'number',
                );
            }
            if ($ventasPorProducto->isNotEmpty()) {
                $menorMovimiento = $productosActivos->map(fn (Producto $producto) => [
                    'nombre' => $producto->nombre,
                    'cantidad' => (int) data_get($ventasPorProducto->get($producto->nombre), 'cantidad', 0),
                ])->sortBy('cantidad')->take(8)->values();
                $graficos[] = $this->graficoUnico('Productos con menor movimiento (unidades vendidas)', $menorMovimiento->pluck('nombre')->all(), $menorMovimiento->pluck('cantidad')->all(), 'number');
            }

            $criticos = (int) Producto::query()->where('activo', true)->whereColumn('stock', '<=', 'stock_minimo')->count();
            $resumenEjecutivo = sprintf('%d productos activos; %d con existencias y %d agotados. %d están en nivel crítico. Valor estimado al costo de compra actual: %s.', $productosActivos->count(), $productosConExistencias, $productosAgotados, $criticos, $this->formatearMoneda($valorInventario));
            $hallazgos[] = $criticos > 0 ? sprintf('%d productos están en o por debajo de su stock mínimo.', $criticos) : 'No hay productos activos en o por debajo del stock mínimo.';
            $hallazgos[] = sprintf('El valor estimado del inventario disponible, con costos de compra actuales, es %s.', $this->formatearMoneda($valorInventario));
            $hallazgos[] = $ventasPorProducto->isEmpty() ? 'No hay registros de ventas suficientes para evaluar movimiento de productos.' : sprintf('El análisis de movimiento usa ventas registradas de %s a %s.', $desde, $hasta);
            $recomendaciones[] = $criticos > 0 ? 'Verifica las ventas recientes y compras pendientes de los productos críticos antes de definir reposiciones.' : 'Mantén el seguimiento periódico del stock y de los mínimos configurados.';
            $recomendaciones[] = 'Prioriza las compras de acuerdo con las unidades vendidas y revisa productos con poca rotación antes de aumentar existencias.';
        } elseif ($reporte === 'compras') {
            $compras = Compra::query()->whereBetween('created_at', [$inicio, $fin])->get(['created_at', 'total', 'proveedor_nombre']);
            $detalleProductos = DetalleCompra::query()
                ->join('compras', 'compras.id', '=', 'detalle_compras.compra_id')
                ->whereBetween('compras.created_at', [$inicio, $fin])
                ->selectRaw('detalle_compras.producto_nombre as nombre, SUM(detalle_compras.cantidad) as cantidad, SUM(detalle_compras.subtotal) as subtotal')
                ->groupBy('detalle_compras.producto_nombre')
                ->orderByDesc('cantidad')
                ->get()
                ->map(fn ($detalle) => [
                    'producto' => $detalle->nombre,
                    'cantidad' => (int) $detalle->cantidad,
                    'subtotal' => (float) $detalle->subtotal,
                ])
                ->all();
            $detalleAnterior = [];
            if ($comparacion['disponible']) {
                $detalleAnterior = DetalleCompra::query()
                    ->join('compras', 'compras.id', '=', 'detalle_compras.compra_id')
                    ->whereBetween('compras.created_at', [
                        $comparacion['desde_anterior'].' 00:00:00',
                        $comparacion['hasta_anterior'].' 23:59:59.999999',
                    ])
                    ->selectRaw('detalle_compras.producto_nombre as nombre, SUM(detalle_compras.subtotal) / NULLIF(SUM(detalle_compras.cantidad), 0) as costo_promedio')
                    ->groupBy('detalle_compras.producto_nombre')
                    ->get()
                    ->keyBy('nombre')
                    ->all();
            }
            $variacionesCosto = [];
            foreach ($detalleProductos as $detalle) {
                $anterior = $detalleAnterior[$detalle['producto']] ?? null;
                $costoActual = $detalle['cantidad'] > 0 ? $detalle['subtotal'] / $detalle['cantidad'] : 0;
                $costoAnterior = (float) data_get($anterior, 'costo_promedio', 0);
                if ($costoAnterior > 0 && $costoActual !== $costoAnterior) {
                    $variacionesCosto[] = [
                        'producto' => $detalle['producto'],
                        'actual' => $costoActual,
                        'anterior' => $costoAnterior,
                        'porcentaje' => (($costoActual - $costoAnterior) / $costoAnterior) * 100,
                    ];
                }
            }
            $porProveedor = $compras->groupBy('proveedor_nombre')->map(fn (Collection $grupo) => (float) $grupo->sum('total'))->sortDesc()->take(8);
            $graficos[] = $this->graficoTemporal('Evolución de compras', $compras, 'total', 'currency');
            if ($porProveedor->isNotEmpty()) {
                $graficos[] = $this->graficoUnico('Compras por proveedor (COP)', $porProveedor->keys()->all(), $porProveedor->values()->all(), 'currency');
            }
            $pendientes = (int) data_get($resumen, '2.value', 0);
            $resumenEjecutivo = sprintf('%d compras por %s; %d pendientes de recepción.', $cantidad, $this->formatearMoneda($total), $pendientes);
            if ($porProveedor->isNotEmpty()) {
                $hallazgos[] = sprintf('El mayor volumen monetario de compras corresponde a %s (%s).', $porProveedor->keys()->first(), $this->formatearMoneda($porProveedor->first()));
            } else {
                $hallazgos[] = 'No se registraron compras en el período seleccionado.';
            }
            foreach (array_slice($variacionesCosto, 0, 5) as $variacion) {
                $hallazgos[] = sprintf('El costo promedio de %s %s %.1f%% frente al período anterior (%s vs. %s por unidad).',
                    $variacion['producto'],
                    $variacion['porcentaje'] > 0 ? 'aumentó' : 'disminuyó',
                    abs($variacion['porcentaje']),
                    $this->formatearMoneda($variacion['actual']),
                    $this->formatearMoneda($variacion['anterior']),
                );
            }
            if ($detalleProductos !== [] && $detalleAnterior === []) {
                $hallazgos[] = 'No hay compras detalladas de productos en el período anterior equivalente para comparar costos unitarios.';
            }
            $hallazgos[] = $pendientes > 0 ? sprintf('Hay %d compras pendientes; no se consideran recibidas ni incrementan automáticamente el inventario.', $pendientes) : 'No hay compras pendientes de recepción en el período.';
            $recomendaciones[] = $this->recomendacionComparacion('El valor de las compras', $comparacion);
            $recomendaciones[] = 'Confirma la recepción de compras pendientes y compara precios por proveedor antes de emitir nuevos pedidos.';
        } elseif ($reporte === 'caja') {
            $cierres = Caja::query()->where('estado', 'cerrada')->whereBetween('cerrada_en', [$inicio, $fin])->orderBy('cerrada_en')->get(['id', 'cerrada_en', 'monto_esperado', 'monto_contado', 'diferencia']);
            $graficos[] = [
                'titulo' => 'Efectivo por cierre',
                'formato' => 'currency',
                'etiquetas' => $cierres->map(fn (Caja $caja) => '#'.$caja->id.' · '.$caja->cerrada_en?->format('d/m'))->all(),
                'series' => [
                    ['nombre' => 'Esperado', 'valores' => $cierres->map(fn (Caja $caja) => (float) $caja->monto_esperado)->all()],
                    ['nombre' => 'Contado', 'valores' => $cierres->map(fn (Caja $caja) => (float) $caja->monto_contado)->all()],
                    ['nombre' => 'Diferencia', 'valores' => $cierres->map(fn (Caja $caja) => (float) $caja->diferencia)->all()],
                ],
            ];
            $faltantes = $cierres->filter(fn (Caja $caja) => (float) $caja->diferencia < 0);
            $sobrantes = $cierres->filter(fn (Caja $caja) => (float) $caja->diferencia > 0);
            $totalFaltantes = abs((float) $faltantes->sum('diferencia'));
            $totalSobrantes = (float) $sobrantes->sum('diferencia');
            $diferencia = (float) data_get($resumen, '3.value', 0);
            $resumenEjecutivo = sprintf('%d cierres; efectivo esperado %s y contado %s. Diferencia acumulada: %s.', $cantidad, $this->formatearMoneda((float) data_get($resumen, '1.value', 0)), $this->formatearMoneda((float) data_get($resumen, '2.value', 0)), $this->formatearMoneda($diferencia));
            $hallazgos[] = sprintf('%d cierres con faltante por %s y %d con sobrante por %s.', $faltantes->count(), $this->formatearMoneda($totalFaltantes), $sobrantes->count(), $this->formatearMoneda($totalSobrantes));
            if ($faltantes->isNotEmpty()) {
                $cierreMayor = $faltantes->sortBy('diferencia')->first();
                $hallazgos[] = sprintf('El mayor faltante fue %s en el cierre #%d.', $this->formatearMoneda((float) $cierreMayor->diferencia), $cierreMayor->id);
            }
            $recomendaciones[] = $faltantes->isNotEmpty() || $sobrantes->isNotEmpty()
                ? 'Verifica individualmente los cierres con diferencias contra ventas en efectivo, ajustes y el conteo registrado.'
                : 'Continúa documentando el conteo y la conciliación en cada cierre.';
        } else {
            $domicilios = Venta::query()->where('tipo_venta', 'domicilio')->whereBetween('created_at', [$inicio, $fin])->get(['created_at', 'total', 'costo_domicilio', 'domicilio_estado']);
            $estados = $domicilios->groupBy(fn (Venta $venta) => ucfirst((string) ($venta->domicilio_estado ?: 'Sin estado')))->map->count();
            $graficos[] = $this->graficoUnico('Domicilios por estado (pedidos)', $estados->keys()->all(), $estados->values()->all(), 'number');
            $graficos[] = $this->graficoSeriesTemporalesDomicilios($domicilios);
            $pendientes = $domicilios->filter(fn (Venta $venta) => $venta->domicilio_estado === 'pendiente')->count();
            $costos = (float) data_get($resumen, '2.value', 0);
            $ventasMostrador = (float) Venta::query()->where('tipo_venta', 'mostrador')->whereBetween('created_at', [$inicio, $fin])->sum('total');
            $resumenEjecutivo = sprintf('%d domicilios con ventas por %s y costos registrados por %s; %d pedidos pendientes.', $cantidad, $this->formatearMoneda($total), $this->formatearMoneda($costos), $pendientes);
            $hallazgos[] = $pendientes > 0 ? sprintf('%d domicilios están pendientes de entrega.', $pendientes) : 'No hay domicilios pendientes de entrega.';
            $hallazgos[] = sprintf('Los costos registrados de domicilio son %s; este valor se presenta por separado de la venta y no representa utilidad.', $this->formatearMoneda($costos));
            $hallazgos[] = sprintf('Ventas por mostrador: %s; ventas por domicilio: %s.', $this->formatearMoneda($ventasMostrador), $this->formatearMoneda($total));
            $recomendaciones[] = $pendientes > 0 ? 'Da seguimiento a los pedidos pendientes y actualiza su estado después de confirmar la entrega.' : 'Continúa registrando los estados de entrega para conservar un seguimiento confiable.';
            $recomendaciones[] = 'Revisa por separado ventas y costos de entrega; se requieren otros costos para calcular la rentabilidad del canal.';
        }

        if ($comparacion['disponible']) {
            $hallazgos[] = $comparacion['porcentaje'] === null
                ? sprintf('El valor del período anterior fue %s; no es posible calcular una variación porcentual desde una base de cero.', $this->formatearMoneda($comparacion['anterior']))
                : sprintf('Comparación con el período anterior equivalente: %s %s (%.1f%%).',
                    $this->formatearMoneda(abs($comparacion['diferencia'])),
                    $comparacion['diferencia'] >= 0 ? 'más' : 'menos',
                    abs($comparacion['porcentaje']),
                );
        }

        return [
            'tipo' => $reporte,
            'titulo' => 'Análisis del periodo y recomendaciones',
            'desde' => $desde,
            'hasta' => $hasta,
            'resumen_ejecutivo' => $resumenEjecutivo,
            'comparacion' => $comparacion,
            'graficos' => array_values(array_filter($graficos, fn (array $grafico) => $grafico['etiquetas'] !== [])),
            'hallazgos' => $hallazgos,
            'conclusion' => $hallazgos[0] ?? 'No hay información suficiente para elaborar conclusiones en este período.',
            'recomendaciones' => $recomendaciones,
            'detalle_productos' => $detalleProductos,
        ];
    }

    private function graficoTemporal(string $titulo, Collection $registros, string $campo, string $formato): array
    {
        $grupos = [];
        foreach ($registros as $registro) {
            $clave = Carbon::parse($registro->created_at)->format('Y-m-d');
            $grupos[$clave] = ($grupos[$clave] ?? 0) + (float) $registro->{$campo};
        }
        ksort($grupos);

        return $this->graficoUnico($titulo, array_map(fn (string $fecha) => Carbon::parse($fecha)->format('d/m/y'), array_keys($grupos)), array_values($grupos), $formato);
    }

    private function graficoSeriesTemporalesDomicilios(Collection $domicilios): array
    {
        $series = ['Entregado' => [], 'Pendiente' => [], 'Otros estados' => []];
        foreach ($domicilios as $domicilio) {
            $clave = Carbon::parse($domicilio->created_at)->format('Y-m-d');
            $estado = match (strtolower((string) $domicilio->domicilio_estado)) {
                'entregado' => 'Entregado',
                'pendiente' => 'Pendiente',
                default => 'Otros estados',
            };
            $series[$estado][$clave] = ($series[$estado][$clave] ?? 0) + 1;
        }
        $fechas = collect($series)->flatMap(fn (array $valores) => array_keys($valores))->unique()->sort()->values();
        $etiquetas = $fechas->map(fn (string $fecha) => Carbon::parse($fecha)->format('d/m/y'))->all();

        return [
            'titulo' => 'Evolución de domicilios por estado (pedidos)',
            'formato' => 'number',
            'etiquetas' => $etiquetas,
            'series' => array_map(fn (string $nombre, array $valores) => [
                'nombre' => $nombre,
                'valores' => $fechas->map(fn (string $fecha) => $valores[$fecha] ?? 0)->all(),
            ], array_keys($series), array_values($series)),
        ];
    }

    private function graficoUnico(string $titulo, array $etiquetas, array $valores, string $formato): array
    {
        return $this->graficoSeries($titulo, $etiquetas, [['nombre' => 'Total', 'valores' => $valores]], $formato);
    }

    private function graficoSeries(string $titulo, array $etiquetas, array $series, string $formato): array
    {
        return [
            'titulo' => $titulo,
            'formato' => $formato,
            'etiquetas' => array_values($etiquetas),
            'series' => array_values($series),
        ];
    }

    private function comparacionPeriodo(string $reporte, string $inicio, string $fin): array
    {
        $inicioCarbon = Carbon::parse($inicio);
        $finCarbon = Carbon::parse($fin);
        $dias = (int) $inicioCarbon->copy()->startOfDay()->diffInDays($finCarbon->copy()->startOfDay()) + 1;
        $anteriorInicio = $inicioCarbon->copy()->subDays($dias);
        $anteriorFin = $inicioCarbon->copy()->subSecond();

        $consulta = match ($reporte) {
            'ventas' => Venta::query(),
            'inventario' => MovimientoInventario::query(),
            'compras' => Compra::query(),
            'caja' => Caja::query()->where('estado', 'cerrada')->whereNotNull('cerrada_en'),
            default => Venta::query()->where('tipo_venta', 'domicilio'),
        };
        $campoFecha = $reporte === 'caja' ? 'cerrada_en' : 'created_at';
        $campoValor = match ($reporte) {
            'ventas', 'compras', 'domicilios' => 'total',
            'inventario', 'caja' => 'diferencia',
            default => 'total',
        };
        $consulta->whereBetween($campoFecha, [$anteriorInicio->format('Y-m-d H:i:s'), $anteriorFin->format('Y-m-d H:i:s.u')]);
        $cantidadAnterior = (clone $consulta)->count();
        $valorAnterior = (float) $consulta->sum($campoValor);
        $valorActual = match ($reporte) {
            'ventas', 'domicilios' => (float) Venta::query()->whereBetween('created_at', [$inicio, $fin])->when($reporte === 'domicilios', fn (Builder $query) => $query->where('tipo_venta', 'domicilio'))->sum('total'),
            'compras' => (float) Compra::query()->whereBetween('created_at', [$inicio, $fin])->sum('total'),
            'inventario' => (float) MovimientoInventario::query()->whereBetween('created_at', [$inicio, $fin])->sum('diferencia'),
            default => (float) Caja::query()->where('estado', 'cerrada')->whereBetween('cerrada_en', [$inicio, $fin])->sum('diferencia'),
        };
        $diferencia = $valorActual - $valorAnterior;

        return [
            'disponible' => $cantidadAnterior > 0,
            'actual' => $valorActual,
            'anterior' => $valorAnterior,
            'diferencia' => $diferencia,
            'porcentaje' => $valorAnterior != 0.0 ? ($diferencia / abs($valorAnterior)) * 100 : null,
            'desde_anterior' => $anteriorInicio->toDateString(),
            'hasta_anterior' => $anteriorFin->toDateString(),
        ];
    }

    private function recomendacionComparacion(string $metrica, array $comparacion): string
    {
        if (! $comparacion['disponible']) {
            return 'No hay registros en el período anterior equivalente para calcular una variación. Continúa registrando la operación para habilitar comparaciones.';
        }

        if ($comparacion['porcentaje'] === null) {
            return sprintf('El período anterior tuvo valor cero y el actual fue %s; no es posible calcular una variación porcentual. Revisa el detalle de operaciones para interpretar el cambio.', $this->formatearMoneda($comparacion['actual']));
        }

        if ($comparacion['diferencia'] < 0) {
            return sprintf('%s disminuyeron %.1f%% frente al período anterior. Revisa los movimientos por fecha y ajusta la planificación según corresponda.', $metrica, abs($comparacion['porcentaje']));
        }

        return sprintf('%s aumentaron %.1f%% frente al período anterior. Revisa qué movimientos contribuyeron a la variación y valida que la tendencia sea sostenible.', $metrica, $comparacion['porcentaje']);
    }

    private function formatearMoneda(float $valor): string
    {
        return '$ '.number_format($valor, 0, ',', '.').' COP';
    }

    private function mapearRegistros(Builder $consulta, bool $paginar, callable $mapear): Collection|\Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        if ($paginar) {
            return $consulta->paginate(15)->withQueryString()->through($mapear);
        }

        return $consulta->get()->map($mapear);
    }

    private function columnas(string $reporte): array
    {
        return match ($reporte) {
            'inventario' => [
                'fecha' => 'Fecha', 'producto' => 'Producto', 'codigo' => 'Código',
                'tipo' => 'Tipo', 'diferencia' => 'Cambio', 'stock_anterior' => 'Stock anterior',
                'stock_nuevo' => 'Stock nuevo', 'motivo' => 'Motivo', 'usuario' => 'Usuario',
            ],
            'compras' => [
                'id' => 'Compra', 'fecha' => 'Fecha', 'proveedor' => 'Proveedor',
                'estado' => 'Estado', 'total' => 'Total', 'recibida_en' => 'Recibida',
            ],
            'caja' => [
                'id' => 'Caja', 'fecha' => 'Cierre', 'usuario' => 'Usuario',
                'monto_inicial' => 'Monto inicial', 'monto_esperado' => 'Esperado',
                'monto_contado' => 'Contado', 'diferencia' => 'Diferencia',
            ],
            'domicilios' => [
                'id' => 'Pedido', 'fecha' => 'Fecha', 'cliente' => 'Cliente',
                'telefono' => 'Teléfono', 'direccion' => 'Dirección', 'estado' => 'Estado',
                'costo_domicilio' => 'Costo domicilio', 'total' => 'Total', 'usuario' => 'Usuario',
            ],
            default => [
                'id' => 'Venta', 'fecha' => 'Fecha', 'usuario' => 'Usuario',
                'tipo' => 'Tipo', 'estado' => 'Estado domicilio',
                'costo_adicional' => 'Costo domicilio', 'total' => 'Total',
            ],
        };
    }

    private function descargarExcel(string $nombreArchivo, string $nombreReporte, string $desde, string $hasta, array $resumen, array $columnas, array $filas, array $analisis)
    {
        $archivoTemporal = tempnam(sys_get_temp_dir(), 'reporte_');
        $zip = new ZipArchive();

        if ($archivoTemporal === false) {
            throw new RuntimeException('No fue posible preparar el archivo Excel del reporte.');
        }

        $abierto = false;

        try {
            if ($zip->open($archivoTemporal, ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('No fue posible preparar el archivo Excel del reporte.');
            }
            $abierto = true;

            $archivos = [
                '[Content_Types].xml' => $this->xmlContentTypes(),
                '_rels/.rels' => $this->xmlRootRelationships(),
                'xl/workbook.xml' => $this->xmlWorkbook(),
                'xl/_rels/workbook.xml.rels' => $this->xmlWorkbookRelationships(),
                'xl/worksheets/sheet1.xml' => $this->xmlWorksheet($nombreReporte, $desde, $hasta, $resumen, $columnas, $filas),
                'xl/worksheets/sheet2.xml' => $this->xmlAnalisisWorksheet($analisis, $resumen),
                'xl/worksheets/sheet3.xml' => $this->xmlGraficosWorksheet($analisis),
                'xl/styles.xml' => $this->xmlStyles(),
            ];

            foreach ($archivos as $ruta => $contenido) {
                if (! $zip->addFromString($ruta, $contenido)) {
                    throw new RuntimeException('No fue posible completar el archivo Excel del reporte.');
                }
            }

            if (! $zip->close()) {
                $abierto = false;
                throw new RuntimeException('No fue posible finalizar el archivo Excel del reporte.');
            }
            $abierto = false;

            return response()->download($archivoTemporal, $nombreArchivo.'.xlsx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])->deleteFileAfterSend(true);
        } catch (\Throwable $exception) {
            if ($abierto) {
                $zip->close();
            }
            if (is_file($archivoTemporal)) {
                unlink($archivoTemporal);
            }

            throw $exception;
        }
    }

    private function xmlContentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet3.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>';
    }

    private function xmlRootRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private function xmlWorkbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="Detalle" sheetId="1" r:id="rId1"/><sheet name="Análisis" sheetId="2" r:id="rId2"/><sheet name="Gráficos" sheetId="3" r:id="rId3"/></sheets></workbook>';
    }

    private function xmlWorkbookRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>'
            .'<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet3.xml"/>'
            .'<Relationship Id="rId4" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
    }

    private function xmlStyles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<numFmts count="2"><numFmt numFmtId="164" formatCode="$ #,##0 &quot;COP&quot;"/><numFmt numFmtId="165" formatCode="0.0&quot;%&quot;"/></numFmts>'
            .'<fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>'
            .'<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/><xf numFmtId="165" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/></cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }

    private function xmlAnalisisWorksheet(array $analisis, array $resumen): string
    {
        $rows = [
            ['Resumen ejecutivo', $analisis['resumen_ejecutivo']],
            ['Período', $analisis['desde'].' al '.$analisis['hasta']],
        ];
        $monedas = [];
        foreach ($resumen as $indicador) {
            $rows[] = [$indicador['label'], $indicador['value']];
            if ($indicador['format'] === 'currency') {
                $monedas['B'.count($rows)] = true;
            }
        }
        if ($analisis['comparacion']['disponible']) {
            $rows[] = ['Período anterior', $analisis['comparacion']['desde_anterior'].' al '.$analisis['comparacion']['hasta_anterior']];
            $rows[] = ['Variación porcentual', $analisis['comparacion']['porcentaje'] ?? 'No calculable (valor anterior en cero)'];
            if ($analisis['comparacion']['porcentaje'] !== null) {
                $monedas['B'.count($rows)] = 2;
            }
        } else {
            $rows[] = ['Comparación', 'Sin datos en el período anterior equivalente'];
        }
        $rows[] = ['Conclusión', $analisis['conclusion']];
        $rows[] = ['Hallazgos', ''];
        foreach ($analisis['hallazgos'] as $hallazgo) {
            $rows[] = [$hallazgo];
        }
        $rows[] = ['Recomendaciones', ''];
        foreach ($analisis['recomendaciones'] as $recomendacion) {
            $rows[] = [$recomendacion];
        }
        if ($analisis['detalle_productos'] !== []) {
            $rows[] = ['Productos adquiridos'];
            $rows[] = ['Producto', 'Cantidad', 'Subtotal COP'];
            foreach ($analisis['detalle_productos'] as $detalle) {
                $rows[] = [$detalle['producto'], $detalle['cantidad'], $detalle['subtotal']];
                $monedas['C'.count($rows)] = true;
            }
        }

        return $this->xmlFilasWorksheet($rows, $monedas);
    }

    private function xmlGraficosWorksheet(array $analisis): string
    {
        $rows = [];
        $monedas = [];
        foreach ($analisis['graficos'] as $grafico) {
            $rows[] = [$grafico['titulo'], 'Formato: '.$grafico['formato']];
            $rows[] = array_merge(['Serie / período'], $grafico['etiquetas']);
            foreach ($grafico['series'] as $serie) {
                $rows[] = array_merge([$serie['nombre']], $serie['valores']);
                if ($grafico['formato'] === 'currency') {
                    $fila = count($rows);
                    foreach (range(2, count($grafico['etiquetas']) + 1) as $columna) {
                        $monedas[$this->columnaExcel($columna).$fila] = true;
                    }
                }
            }
            $rows[] = [];
        }

        if ($rows === []) {
            $rows[] = ['No hay datos suficientes para construir gráficos en este período.'];
        }

        return $this->xmlFilasWorksheet($rows, $monedas);
    }

    private function xmlFilasWorksheet(array $rows, array $monedas = []): string
    {
        $sheetRows = '';
        foreach ($rows as $rowNumber => $cells) {
            $sheetRows .= '<row r="'.($rowNumber + 1).'">';
            foreach (array_values($cells) as $columnIndex => $value) {
                $reference = $this->columnaExcel($columnIndex + 1).($rowNumber + 1);
                if (is_int($value) || is_float($value)) {
                    $estiloId = $monedas[$reference] ?? 0;
                    $estilo = $estiloId > 0 ? ' s="'.$estiloId.'"' : '';
                    $sheetRows .= '<c r="'.$reference.'"'.$estilo.'><v>'.$value.'</v></c>';
                } else {
                    $text = htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                    $sheetRows .= '<c r="'.$reference.'" t="inlineStr"><is><t xml:space="preserve">'.$text.'</t></is></c>';
                }
            }
            $sheetRows .= '</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$sheetRows.'</sheetData></worksheet>';
    }

    private function xmlWorksheet(string $nombreReporte, string $desde, string $hasta, array $resumen, array $columnas, array $filas): string
    {
        $rows = [];
        $rows[] = ['Reporte de '.$nombreReporte];
        $rows[] = ['Rango de fechas', $desde.' al '.$hasta];
        foreach ($resumen as $dato) {
            $rows[] = [$dato['label'], $dato['value']];
        }
        $rows[] = [];
        $rows[] = array_values($columnas);

        foreach ($filas as $fila) {
            $rows[] = array_map(fn (string $key) => $fila[$key] ?? '', array_keys($columnas));
        }

        $sheetRows = '';
        foreach ($rows as $rowNumber => $cells) {
            $sheetRows .= '<row r="'.($rowNumber + 1).'">';
            foreach (array_values($cells) as $columnIndex => $value) {
                $reference = $this->columnaExcel($columnIndex + 1).($rowNumber + 1);
                if (is_int($value) || is_float($value)) {
                    $currency = false;
                    if ($rowNumber >= 6) {
                        $key = array_keys($columnas)[$columnIndex] ?? null;
                        $currency = in_array($key, ['total', 'costo_adicional', 'costo_domicilio', 'monto_inicial', 'monto_esperado', 'monto_contado'], true)
                            || ($key === 'diferencia' && $nombreReporte === 'Caja');
                    }
                    if ($rowNumber >= 2 && $rowNumber <= 4 && ($resumen[$rowNumber - 2]['format'] ?? null) === 'currency') {
                        $currency = true;
                    }
                    $estilo = $currency ? ' s="1"' : '';
                    $sheetRows .= '<c r="'.$reference.'"'.$estilo.'><v>'.$value.'</v></c>';
                } else {
                    $text = htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                    $sheetRows .= '<c r="'.$reference.'" t="inlineStr"><is><t xml:space="preserve">'.$text.'</t></is></c>';
                }
            }
            $sheetRows .= '</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$sheetRows.'</sheetData></worksheet>';
    }

    private function columnaExcel(int $numero): string
    {
        $letras = '';
        while ($numero > 0) {
            $resto = ($numero - 1) % 26;
            $letras = chr(65 + $resto).$letras;
            $numero = intdiv($numero - 1, 26);
        }

        return $letras;
    }
}
