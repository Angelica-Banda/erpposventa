<?php

namespace App\Http\Controllers;

use App\Models\DetalleVenta;
use App\Models\Producto;
use App\Models\Venta;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'periodo' => ['nullable', Rule::in(['hoy', 'semana'])],
        ]);
        $periodo = $filters['periodo'] ?? 'hoy';
        $ahora = now();
        $inicio = $periodo === 'semana' ? $ahora->copy()->startOfWeek() : $ahora->copy()->startOfDay();
        $fin = $periodo === 'semana' ? $ahora->copy()->endOfWeek() : $ahora->copy()->endOfDay();
        $ventas = Venta::query()->whereBetween('created_at', [$inicio, $fin]);
        $ventasResumen = (clone $ventas)
            ->selectRaw('COUNT(*) as transacciones, COALESCE(SUM(total), 0) as total')
            ->first();
        $unidadesVendidas = DetalleVenta::query()
            ->join('ventas', 'ventas.id', '=', 'detalle_ventas.venta_id')
            ->whereBetween('ventas.created_at', [$inicio, $fin])
            ->sum('detalle_ventas.cantidad');

        $stockBajo = Producto::query()
            ->where('activo', true)
            ->whereColumn('stock', '<=', 'stock_minimo');
        $productosBajoStock = (clone $stockBajo)
            ->with('categoria:id,nombre')
            ->orderBy('stock')
            ->orderBy('nombre')
            ->limit(5)
            ->get(['id', 'categoria_id', 'codigo', 'nombre', 'stock', 'stock_minimo']);
        $productosCriticos = (clone $stockBajo)->where('stock', '<=', 0)->count();

        $domiciliosPendientes = Venta::query()
            ->where('tipo_venta', 'domicilio')
            ->where('domicilio_estado', 'pendiente')
            ->count();
        $domiciliosEntregados = Venta::query()
            ->where('tipo_venta', 'domicilio')
            ->where('domicilio_estado', 'entregado')
            ->whereBetween('created_at', [$inicio, $fin])
            ->count();

        $ventasDelPeriodo = (clone $ventas)->get(['total', 'created_at']);
        $grafico = $periodo === 'semana'
            ? $this->graficoSemanal($inicio, $ventasDelPeriodo)
            : $this->graficoDiario($ventasDelPeriodo);

        return Inertia::render('Dashboard', [
            'periodoActual' => $periodo,
            'kpis' => [
                'ventas' => [
                    'valor' => (float) $ventasResumen->total,
                    'transacciones' => (int) $ventasResumen->transacciones,
                ],
                'unidadesVendidas' => (int) $unidadesVendidas,
                'stockBajo' => [
                    'cantidad' => (clone $stockBajo)->count(),
                    'criticos' => $productosCriticos,
                ],
                'domicilios' => [
                    'pendientes' => $domiciliosPendientes,
                    'entregadosPeriodo' => $domiciliosEntregados,
                ],
            ],
            'grafico' => $grafico,
            'productosBajoStock' => $productosBajoStock->map(fn (Producto $producto) => [
                'id' => $producto->id,
                'nombre' => $producto->nombre,
                'codigo' => $producto->codigo,
                'stock' => (int) $producto->stock,
                'stock_minimo' => (int) $producto->stock_minimo,
                'categoria' => $producto->categoria?->nombre,
            ])->values(),
        ]);
    }

    private function graficoDiario($ventas): array
    {
        $bloques = collect(range(0, 5))->map(fn (int $bloque) => [
            'etiqueta' => sprintf('%02d-%02d', $bloque * 4, $bloque * 4 + 3),
            'total' => 0.0,
            'transacciones' => 0,
        ]);

        foreach ($ventas as $venta) {
            $bloque = intdiv((int) Carbon::parse($venta->created_at)->format('G'), 4);
            $barra = $bloques->get($bloque);
            $barra['total'] += (float) $venta->total;
            $barra['transacciones']++;
            $bloques->put($bloque, $barra);
        }

        return $this->normalizarGrafico($bloques->all());
    }

    private function graficoSemanal(Carbon $inicio, $ventas): array
    {
        $dias = collect(range(0, 6))->map(fn (int $offset) => [
            'fecha' => $inicio->copy()->addDays($offset),
            'total' => 0.0,
            'transacciones' => 0,
        ]);
        $indicePorFecha = $dias->mapWithKeys(fn (array $dia, int $indice) => [
            $dia['fecha']->toDateString() => $indice,
        ]);

        foreach ($ventas as $venta) {
            $fecha = Carbon::parse($venta->created_at)->toDateString();
            if (! $indicePorFecha->has($fecha)) {
                continue;
            }

            $indice = $indicePorFecha->get($fecha);
            $dia = $dias->get($indice);
            $dia['total'] += (float) $venta->total;
            $dia['transacciones']++;
            $dias->put($indice, $dia);
        }

        $nombresDias = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];

        return $this->normalizarGrafico($dias->map(fn (array $dia) => [
            'etiqueta' => $nombresDias[(int) $dia['fecha']->format('w')].' '.$dia['fecha']->format('d/m'),
            'total' => $dia['total'],
            'transacciones' => $dia['transacciones'],
        ])->all());
    }

    private function normalizarGrafico(array $barras): array
    {
        $maximo = max(array_column($barras, 'total')) ?: 0;

        return array_map(fn (array $barra) => [
            ...$barra,
            'altura' => $maximo > 0 ? max(4, (int) round($barra['total'] / $maximo * 100)) : 0,
        ], $barras);
    }
}
