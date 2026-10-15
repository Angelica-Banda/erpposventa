<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->string('tipo_venta', 20)->default('mostrador');
            $table->string('cliente_nombre')->nullable();
            $table->string('cliente_telefono', 30)->nullable();
            $table->text('domicilio_direccion')->nullable();
            $table->decimal('costo_domicilio', 12, 2)->default(0);
            $table->string('domicilio_estado', 20)->nullable();

            $table->index(['tipo_venta', 'domicilio_estado']);
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropIndex(['tipo_venta', 'domicilio_estado']);
            $table->dropColumn([
                'tipo_venta',
                'cliente_nombre',
                'cliente_telefono',
                'domicilio_direccion',
                'costo_domicilio',
                'domicilio_estado',
            ]);
        });
    }
};
