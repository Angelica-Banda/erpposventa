<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovimientoInventario extends Model
{
    use HasFactory;

    protected $table = 'movimientos_inventario';

    protected $fillable = [
        'producto_id',
        'producto_codigo',
        'producto_nombre',
        'tipo',
        'stock_anterior',
        'stock_nuevo',
        'diferencia',
        'motivo',
        'usuario_id',
        'usuario_nombre',
    ];

    protected $casts = [
        'stock_anterior' => 'integer',
        'stock_nuevo' => 'integer',
        'diferencia' => 'integer',
    ];

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
