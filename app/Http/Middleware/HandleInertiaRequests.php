<?php

namespace App\Http\Middleware;

use App\Models\Empresa;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $usuario = $request->user();

        // Obtener configuración de empresa con fallback defensivo
        $empresaData = null;
        try {
            $empresa = Empresa::obtenerConfiguracion();
            $empresaData = [
                'id' => $empresa->id,
                'razon_social' => $empresa->razon_social,
                'nombre_comercial' => $empresa->nombre_comercial,
                'nit' => $empresa->nit,
                'dv' => $empresa->dv,
                'direccion' => $empresa->direccion,
                'pais' => $empresa->pais,
                'departamento' => $empresa->departamento,
                'municipio' => $empresa->municipio,
                'codigo_postal' => $empresa->codigo_postal,
                'telefono' => $empresa->telefono,
                'email' => $empresa->email,
                'sitio_web' => $empresa->sitio_web,
                'tipo_persona' => $empresa->tipo_persona,
                'regimen_tributario' => $empresa->regimen_tributario,
                'responsabilidades_fiscales' => $empresa->responsabilidades_fiscales,
                'ciiu' => $empresa->ciiu,
                'detalles_tributarios' => $empresa->detalles_tributarios,
                'logo_url' => $empresa->logo_url,
            ];
        } catch (\Throwable $e) {
            $empresaData = [
                'razon_social' => 'PosVenta S.A.S.',
                'nombre_comercial' => 'PosVenta',
                'nit' => '',
                'dv' => '',
                'direccion' => '',
                'telefono' => '',
                'email' => '',
                'logo_url' => null,
            ];
        }

        return [
            ...parent::share($request),
            'auth' => [
                'usuario' => $usuario ? array_merge($usuario->toArray(), [
                    'roles' => $usuario->roles()->pluck('nombre')->toArray(),
                ]) : null,
            ],
            'empresa' => $empresaData,
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ],
        ];
    }
}
