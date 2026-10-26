<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('caja_ajustes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caja_id')->constrained('cajas')->restrictOnDelete();
            $table->foreignId('usuario_admin_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->string('usuario_admin_nombre');
            $table->decimal('monto_esperado_anterior', 12, 2);
            $table->decimal('monto_esperado_nuevo', 12, 2);
            $table->text('motivo');
            $table->timestamps();

            $table->index(['caja_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caja_ajustes');
    }
};
