<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Renombra tablas y relaciones sin eliminar los registros existentes.
    public function up(): void
    {
        if (! DB::connection()->pretending()) {
            foreach (['roles', 'role_users', 'ventas', 'movimientos_inventario'] as $table) {
                if (! Schema::hasTable($table)) {
                    throw new RuntimeException("No se puede normalizar la base de datos: falta la tabla {$table}.");
                }
            }

            if (! Schema::hasTable('users') || Schema::hasTable('usuarios') || Schema::hasTable('roles_usuarios')) {
                throw new RuntimeException('No se puede normalizar la base de datos: las tablas de origen o destino no coinciden con el estado esperado.');
            }

            if (
                DB::table('roles')->where('name', 'superadmin')->exists()
                && DB::table('roles')->where('name', 'admin')->exists()
            ) {
                throw new RuntimeException('Existen los roles superadmin y admin. Deben revisarse sus asignaciones antes de renombrar para evitar conflictos.');
            }
        }

        Schema::table('role_users', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['role_id']);
            $table->dropUnique(['user_id', 'role_id']);
        });

        Schema::table('ventas', function (Blueprint $table) {
            $table->dropForeign(['usuario_id']);
        });

        Schema::table('movimientos_inventario', function (Blueprint $table) {
            $table->dropForeign(['usuario_id']);
        });

        Schema::rename('users', 'usuarios');
        Schema::rename('role_users', 'roles_usuarios');

        Schema::table('roles_usuarios', function (Blueprint $table) {
            $table->renameColumn('user_id', 'usuario_id');
            $table->renameColumn('role_id', 'rol_id');
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->renameColumn('name', 'nombre');
            $table->renameColumn('display_name', 'nombre_visible');
            $table->renameColumn('description', 'descripcion');
        });

        DB::table('roles')
            ->where('nombre', 'superadmin')
            ->update([
                'nombre' => 'admin',
                'nombre_visible' => 'Administrador',
            ]);

        DB::table('usuarios')
            ->where('email', 'superadmin@posventa.com')
            ->where('name', 'Super Administrador')
            ->update(['name' => 'Administrador']);

        Schema::table('roles_usuarios', function (Blueprint $table) {
            $table->foreign('usuario_id')->references('id')->on('usuarios')->cascadeOnDelete();
            $table->foreign('rol_id')->references('id')->on('roles')->cascadeOnDelete();
            $table->unique(['usuario_id', 'rol_id']);
        });

        Schema::table('ventas', function (Blueprint $table) {
            $table->foreign('usuario_id')->references('id')->on('usuarios')->nullOnDelete();
        });

        Schema::table('movimientos_inventario', function (Blueprint $table) {
            $table->foreign('usuario_id')->references('id')->on('usuarios')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! DB::connection()->pretending()) {
            foreach (['roles', 'roles_usuarios', 'ventas', 'movimientos_inventario', 'usuarios'] as $table) {
                if (! Schema::hasTable($table)) {
                    throw new RuntimeException("No se puede revertir la normalización: falta la tabla {$table}.");
                }
            }
        }

        Schema::table('roles_usuarios', function (Blueprint $table) {
            $table->dropForeign(['usuario_id']);
            $table->dropForeign(['rol_id']);
            $table->dropUnique(['usuario_id', 'rol_id']);
        });

        Schema::table('ventas', function (Blueprint $table) {
            $table->dropForeign(['usuario_id']);
        });

        Schema::table('movimientos_inventario', function (Blueprint $table) {
            $table->dropForeign(['usuario_id']);
        });

        DB::table('roles')
            ->where('nombre', 'admin')
            ->update([
                'nombre' => 'superadmin',
                'nombre_visible' => 'Super Administrador',
            ]);

        DB::table('usuarios')
            ->where('email', 'superadmin@posventa.com')
            ->where('name', 'Administrador')
            ->update(['name' => 'Super Administrador']);

        Schema::table('roles', function (Blueprint $table) {
            $table->renameColumn('nombre', 'name');
            $table->renameColumn('nombre_visible', 'display_name');
            $table->renameColumn('descripcion', 'description');
        });

        Schema::rename('roles_usuarios', 'role_users');
        Schema::table('role_users', function (Blueprint $table) {
            $table->renameColumn('usuario_id', 'user_id');
            $table->renameColumn('rol_id', 'role_id');
        });
        Schema::rename('usuarios', 'users');

        Schema::table('role_users', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
            $table->unique(['user_id', 'role_id']);
        });

        Schema::table('ventas', function (Blueprint $table) {
            $table->foreign('usuario_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('movimientos_inventario', function (Blueprint $table) {
            $table->foreign('usuario_id')->references('id')->on('users')->nullOnDelete();
        });
    }
};
