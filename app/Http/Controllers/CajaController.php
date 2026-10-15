<?php

namespace App\Http\Controllers;

use App\Models\Caja;
use App\Models\Usuario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CajaController extends Controller
{
    public function index(Request $request): Response
    {
        $esAdministrador = $request->user()->tieneRol('admin');
        $filtros = $request->validate([
            'usuario' => ['nullable', 'string', 'max:100'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
        ]);

        $cajaAbierta = Caja::query()
            ->where('usuario_id', $request->user()->id)
            ->where('estado', 'abierta')
            ->withSum('ventas as ventas_total', 'total')
            ->first();

        $historial = Caja::query()
            ->where('usuario_id', $request->user()->id)
            ->where('estado', 'cerrada')
            ->withSum('ventas as ventas_total', 'total')
            ->latest('cerrada_en')
            ->paginate(10)
            ->through(fn (Caja $caja) => $this->presentarCaja($caja));

        $reporteCierres = null;
        $resumenCierres = null;

        if ($esAdministrador) {
            $consultaCierres = Caja::query()->where('estado', 'cerrada');

            if (! empty($filtros['usuario'])) {
                $consultaCierres->where('usuario_nombre', 'like', '%'.$filtros['usuario'].'%');
            }

            if (! empty($filtros['desde'])) {
                $consultaCierres->whereDate('cerrada_en', '>=', $filtros['desde']);
            }

            if (! empty($filtros['hasta'])) {
                $consultaCierres->whereDate('cerrada_en', '<=', $filtros['hasta']);
            }

            $resumen = (clone $consultaCierres)
                ->selectRaw('COUNT(*) as total_cierres, COALESCE(SUM(monto_esperado), 0) as monto_esperado, COALESCE(SUM(monto_contado), 0) as monto_contado, COALESCE(SUM(diferencia), 0) as diferencia')
                ->first();

            $reporteCierres = $consultaCierres
                ->withSum('ventas as ventas_total', 'total')
                ->latest('cerrada_en')
                ->paginate(10)
                ->withQueryString()
                ->through(fn (Caja $caja) => $this->presentarCaja($caja));

            $resumenCierres = [
                'total_cierres' => (int) $resumen->total_cierres,
                'monto_esperado' => (float) $resumen->monto_esperado,
                'monto_contado' => (float) $resumen->monto_contado,
                'diferencia' => (float) $resumen->diferencia,
            ];
        }

        return Inertia::render('Caja/Index', [
            'cajaAbierta' => $cajaAbierta ? $this->presentarCaja($cajaAbierta) : null,
            'historial' => $historial,
            'esAdministrador' => $esAdministrador,
            'filtrosReporte' => [
                'usuario' => $filtros['usuario'] ?? '',
                'desde' => $filtros['desde'] ?? '',
                'hasta' => $filtros['hasta'] ?? '',
            ],
            'reporteCierres' => $reporteCierres,
            'resumenCierres' => $resumenCierres,
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ],
        ]);
    }

    public function open(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'monto_inicial' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
        ]);

        DB::transaction(function () use ($request, $validated) {
            $usuario = Usuario::query()
                ->whereKey($request->user()->id)
                ->lockForUpdate()
                ->firstOrFail();

            $alreadyOpen = Caja::query()
                ->where('usuario_id', $usuario->id)
                ->where('estado', 'abierta')
                ->exists();

            if ($alreadyOpen) {
                throw ValidationException::withMessages([
                    'caja' => 'Ya tienes una caja abierta. Debes cerrarla antes de abrir otra.',
                ]);
            }

            Caja::create([
                'usuario_id' => $usuario->id,
                'usuario_nombre' => $usuario->name,
                'monto_inicial' => round((float) $validated['monto_inicial'], 2),
                'estado' => 'abierta',
                'abierta_en' => now(),
            ]);
        });

        return redirect()->route('caja.index')->with('success', 'Caja abierta correctamente.');
    }

    public function close(Request $request, Caja $caja): RedirectResponse
    {
        $validated = $request->validate([
            'monto_contado' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
        ]);

        DB::transaction(function () use ($request, $caja, $validated) {
            $lockedCaja = Caja::query()
                ->whereKey($caja->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $lockedCaja->usuario_id !== (int) $request->user()->id) {
                abort(403);
            }

            if ($lockedCaja->estado !== 'abierta') {
                throw ValidationException::withMessages([
                    'caja' => 'Esta caja ya está cerrada.',
                ]);
            }

            $ventas = (float) $lockedCaja->ventas()->sum('total');
            $esperado = round((float) $lockedCaja->monto_inicial + $ventas, 2);
            $contado = round((float) $validated['monto_contado'], 2);

            $lockedCaja->update([
                'monto_esperado' => $esperado,
                'monto_contado' => $contado,
                'diferencia' => round($contado - $esperado, 2),
                'estado' => 'cerrada',
                'cerrada_en' => now(),
            ]);
        });

        return redirect()->route('caja.index')->with('success', 'Caja cerrada y conciliación registrada.');
    }

    private function presentarCaja(Caja $caja): array
    {
        $ventas = round((float) ($caja->ventas_total ?? 0), 2);
        $esperado = $caja->estado === 'cerrada'
            ? (float) $caja->monto_esperado
            : round((float) $caja->monto_inicial + $ventas, 2);

        return [
            'id' => $caja->id,
            'usuario_nombre' => $caja->usuario_nombre,
            'estado' => $caja->estado,
            'monto_inicial' => (float) $caja->monto_inicial,
            'ventas_total' => $ventas,
            'monto_esperado' => $esperado,
            'monto_contado' => $caja->monto_contado !== null ? (float) $caja->monto_contado : null,
            'diferencia' => $caja->diferencia !== null ? (float) $caja->diferencia : null,
            'abierta_en' => $caja->abierta_en?->format('d/m/Y H:i'),
            'cerrada_en' => $caja->cerrada_en?->format('d/m/Y H:i'),
        ];
    }
}
