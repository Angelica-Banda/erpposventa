<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('caja_ajustes', function (Blueprint $table) {
            $table->renameColumn('monto_esperado_anterior', 'monto_contado_anterior');
            $table->renameColumn('monto_esperado_nuevo', 'monto_contado_nuevo');
        });
    }

    public function down(): void
    {
        Schema::table('caja_ajustes', function (Blueprint $table) {
            $table->renameColumn('monto_contado_anterior', 'monto_esperado_anterior');
            $table->renameColumn('monto_contado_nuevo', 'monto_esperado_nuevo');
        });
    }
};
