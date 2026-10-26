<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CajaAjuste extends Model
{
    protected $table = 'caja_ajustes';

    protected $fillable = [
        'caja_id',
        'usuario_admin_id',
        'usuario_admin_nombre',
        'monto_contado_anterior',
        'monto_contado_nuevo',
        'motivo',
    ];

    protected $casts = [
        'monto_contado_anterior' => 'decimal:2',
        'monto_contado_nuevo' => 'decimal:2',
    ];

    public function caja(): BelongsTo
    {
        return $this->belongsTo(Caja::class, 'caja_id');
    }

    public function administrador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_admin_id');
    }
}
