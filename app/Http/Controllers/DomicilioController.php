<?php

namespace App\Http\Controllers;

use App\Models\Venta;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DomicilioController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'busqueda' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', Rule::in(['pendiente', 'entregado'])],
        ]);

        $domicilios = Venta::query()
            ->where('usuario_id', $request->user()->id)
            ->where('tipo_venta', 'domicilio');

        if (! empty($filters['busqueda'])) {
            $term = trim($filters['busqueda']);
            $domicilios->where(function (Builder $query) use ($term) {
                $query->where('cliente_nombre', 'like', '%'.$term.'%')
                    ->orWhere('cliente_telefono', 'like', '%'.$term.'%')
                    ->orWhere('domicilio_direccion', 'like', '%'.$term.'%');

                if (ctype_digit($term)) {
                    $query->orWhere('id', (int) $term);
                }
            });
        }

        if (! empty($filters['estado'])) {
            $domicilios->where('domicilio_estado', $filters['estado']);
        }

        return Inertia::render('Domicilios/Index', [
            'domicilios' => $domicilios
                ->latest()
                ->paginate(10)
                ->withQueryString()
                ->through(fn (Venta $venta) => [
                    'id' => $venta->id,
                    'cliente_nombre' => $venta->cliente_nombre,
                    'cliente_telefono' => $venta->cliente_telefono,
                    'domicilio_direccion' => $venta->domicilio_direccion,
                    'costo_domicilio' => (float) $venta->costo_domicilio,
                    'estado' => $venta->domicilio_estado,
                    'total' => (float) $venta->total,
                    'creado_en' => $venta->created_at?->format('d/m/Y H:i'),
                ]),
            'filters' => [
                'busqueda' => $filters['busqueda'] ?? '',
                'estado' => $filters['estado'] ?? '',
            ],
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ],
        ]);
    }

    public function markDelivered(Request $request, Venta $venta): RedirectResponse
    {
        DB::transaction(function () use ($request, $venta) {
            $lockedVenta = Venta::query()
                ->whereKey($venta->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $lockedVenta->usuario_id !== (int) $request->user()->id) {
                abort(403);
            }

            if ($lockedVenta->tipo_venta !== 'domicilio') {
                abort(404);
            }

            if ($lockedVenta->domicilio_estado !== 'pendiente') {
                throw ValidationException::withMessages([
                    'domicilio_estado' => 'Este domicilio ya fue marcado como entregado.',
                ]);
            }

            $lockedVenta->update(['domicilio_estado' => 'entregado']);
        });

        return back()->with('success', 'Domicilio #'.$venta->id.' marcado como entregado.');
    }
}
