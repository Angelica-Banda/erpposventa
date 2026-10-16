<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('empresas', function (Blueprint $table) {
            $table->id();

            // Información básica y comercial
            $table->string('razon_social');
            $table->string('nombre_comercial');
            $table->string('nit', 20);
            $table->string('dv', 2)->nullable();
            $table->string('direccion')->nullable();
            $table->string('pais', 100)->default('Colombia');
            $table->string('departamento', 100)->nullable();
            $table->string('municipio', 100)->nullable();
            $table->string('codigo_postal', 20)->nullable();
            $table->string('telefono', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('sitio_web')->nullable();
            $table->string('logo_path')->nullable();

            // Información tributaria (DIAN Colombia)
            $table->string('tipo_persona', 20)->default('juridica'); // natural, juridica
            $table->string('regimen_tributario', 100)->nullable(); // Responsable de IVA, No responsable, etc.
            $table->json('responsabilidades_fiscales')->nullable(); // O-13, O-15, O-23, R-99-PN, etc.
            $table->string('ciiu', 10)->nullable(); // Código actividad económica principal
            $table->text('detalles_tributarios')->nullable(); // Notas, resoluciones DIAN o info adicional

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('empresas');
    }
};
