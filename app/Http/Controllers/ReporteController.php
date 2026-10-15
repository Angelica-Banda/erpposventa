<?php

namespace App\Http\Controllers;

use App\Models\Caja;
use App\Models\Compra;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\Venta;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

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
            'inventario' => $this->reporteInventario($fechaInicio, $fechaFin),
            'compras' => $this->reporteCompras($fechaInicio, $fechaFin),
            'caja' => $this->reporteCaja($fechaInicio, $fechaFin),
            'domicilios' => $this->reporteDomicilios($fechaInicio, $fechaFin),
            default => $this->reporteVentas($fechaInicio, $fechaFin),
        };

        return Inertia::render('Reportes/Index', [
            'reporteActual' => $reporte,
            'filtros' => [
                'desde' => $desde,
                'hasta' => $hasta,
            ],
            'resumen' => $resumen,
            'registros' => $registros,
        ]);
    }

    private function reporteVentas(string $inicio, string $fin): array
    {
        $consulta = Venta::query()->whereBetween('created_at', [$inicio, $fin]);
        $resumen = (clone $consulta)
            ->selectRaw('COUNT(*) as cantidad, COALESCE(SUM(total), 0) as total, COALESCE(SUM(costo_domicilio), 0) as domicilios')
            ->first();

        $registros = $consulta->latest()->paginate(15)->withQueryString()->through(fn (Venta $venta) => [
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

    private function reporteInventario(string $inicio, string $fin): array
    {
        $consulta = MovimientoInventario::query()->whereBetween('created_at', [$inicio, $fin]);
        $resumen = (clone $consulta)
            ->selectRaw('COUNT(*) as cantidad, COALESCE(SUM(CASE WHEN diferencia > 0 THEN diferencia ELSE 0 END), 0) as entradas, COALESCE(SUM(CASE WHEN diferencia < 0 THEN ABS(diferencia) ELSE 0 END), 0) as salidas')
            ->first();

        $productosBajoStock = Producto::query()
            ->where('activo', true)
            ->whereColumn('stock', '<=', 'stock_minimo')
            ->count();

        $registros = $consulta->latest()->paginate(15)->withQueryString()->through(fn (MovimientoInventario $movimiento) => [
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

    private function reporteCompras(string $inicio, string $fin): array
    {
        $consulta = Compra::query()->whereBetween('created_at', [$inicio, $fin]);
        $resumen = (clone $consulta)
            ->selectRaw("COUNT(*) as cantidad, COALESCE(SUM(total), 0) as total, SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes, SUM(CASE WHEN estado = 'recibida' THEN 1 ELSE 0 END) as recibidas")
            ->first();

        $registros = $consulta->latest()->paginate(15)->withQueryString()->through(fn (Compra $compra) => [
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

    private function reporteCaja(string $inicio, string $fin): array
    {
        $consulta = Caja::query()
            ->where('estado', 'cerrada')
            ->whereBetween('cerrada_en', [$inicio, $fin]);
        $resumen = (clone $consulta)
            ->selectRaw('COUNT(*) as cantidad, COALESCE(SUM(monto_esperado), 0) as esperado, COALESCE(SUM(monto_contado), 0) as contado, COALESCE(SUM(diferencia), 0) as diferencia')
            ->first();

        $registros = $consulta->latest('cerrada_en')->paginate(15)->withQueryString()->through(fn (Caja $caja) => [
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

    private function reporteDomicilios(string $inicio, string $fin): array
    {
        $consulta = Venta::query()
            ->where('tipo_venta', 'domicilio')
            ->whereBetween('created_at', [$inicio, $fin]);
        $resumen = (clone $consulta)
            ->selectRaw("COUNT(*) as cantidad, COALESCE(SUM(total), 0) as total, COALESCE(SUM(costo_domicilio), 0) as costos, SUM(CASE WHEN domicilio_estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes, SUM(CASE WHEN domicilio_estado = 'entregado' THEN 1 ELSE 0 END) as entregados")
            ->first();

        $registros = $consulta->latest()->paginate(15)->withQueryString()->through(fn (Venta $venta) => [
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
}
