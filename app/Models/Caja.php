<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Caja extends Model
{
    use HasFactory;

    protected $table = 'cajas';

    protected $fillable = [
        'usuario_id',
        'usuario_nombre',
        'monto_inicial',
        'monto_esperado',
        'monto_contado',
        'diferencia',
        'estado',
        'abierta_en',
        'cerrada_en',
    ];

    protected $casts = [
        'monto_inicial' => 'decimal:2',
        'monto_esperado' => 'decimal:2',
        'monto_contado' => 'decimal:2',
        'diferencia' => 'decimal:2',
        'abierta_en' => 'datetime',
        'cerrada_en' => 'datetime',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class, 'caja_id');
    }

    public function ajustes(): HasMany
    {
        return $this->hasMany(CajaAjuste::class, 'caja_id');
    }
}
