<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Empresa extends Model
{
    use HasFactory;

    protected $table = 'empresas';

    protected $fillable = [
        'razon_social',
        'nombre_comercial',
        'nit',
        'dv',
        'direccion',
        'pais',
        'departamento',
        'municipio',
        'codigo_postal',
        'telefono',
        'email',
        'sitio_web',
        'logo_path',
        'tipo_persona',
        'regimen_tributario',
        'responsabilidades_fiscales',
        'ciiu',
        'detalles_tributarios',
    ];

    protected $casts = [
        'responsabilidades_fiscales' => 'array',
    ];

    protected $appends = [
        'logo_url',
    ];

    /**
     * URL pública del logo de la empresa.
     */
    public function getLogoUrlAttribute(): ?string
    {
        if ($this->logo_path && Storage::disk('public')->exists($this->logo_path)) {
            return asset('storage/' . $this->logo_path);
        }

        return null;
    }

    /**
     * Obtiene la configuración única de la empresa o crea una predeterminada.
     */
    public static function obtenerConfiguracion(): self
    {
        $empresa = static::first();

        if (! $empresa) {
            $empresa = static::create([
                'razon_social' => 'PosVenta S.A.S.',
                'nombre_comercial' => 'PosVenta',
                'nit' => '901234567',
                'dv' => '8',
                'direccion' => 'Calle 50 # 45-20',
                'pais' => 'Colombia',
                'departamento' => 'Antioquia',
                'municipio' => 'Medellín',
                'codigo_postal' => '050001',
                'telefono' => '(604) 444-0000',
                'email' => 'contacto@posventaerp.com',
                'sitio_web' => 'https://posventaerp.com',
                'tipo_persona' => 'juridica',
                'regimen_tributario' => 'Responsable de IVA',
                'responsabilidades_fiscales' => ['R-99-PN'],
                'ciiu' => '4711',
                'detalles_tributarios' => 'Comercio al por menor en establecimientos no especializados.',
            ]);
        }

        return $empresa;
    }
}
