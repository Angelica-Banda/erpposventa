<?php

namespace Tests\Feature;

use App\Models\Compra;
use App\Models\Proveedor;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as AssertableInertia;
use Tests\TestCase;

class ProveedorTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_update_and_delete_a_supplier_without_purchases(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->post(route('proveedores.store'), [
                'nombre' => 'Proveedor inicial',
                'nit' => '900123456',
                'telefono' => '3001234567',
                'correo' => 'compras@example.com',
                'direccion' => 'Calle 10',
                'activo' => true,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $proveedor = Proveedor::where('nit', '900123456')->firstOrFail();

        $this->put(route('proveedores.update', $proveedor), [
            'nombre' => 'Proveedor actualizado',
            'nit' => '900123456',
            'telefono' => '3007654321',
            'correo' => 'nuevo@example.com',
            'direccion' => 'Carrera 20',
            'activo' => true,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame('Proveedor actualizado', $proveedor->fresh()->nombre);

        $this->delete(route('proveedores.destroy', $proveedor))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseMissing('proveedores', ['id' => $proveedor->id]);
    }

    public function test_supplier_with_purchases_cannot_be_deleted(): void
    {
        $admin = $this->createAdmin();
        $proveedor = Proveedor::create(['nombre' => 'Proveedor con historial']);
        Compra::create([
            'proveedor_id' => $proveedor->id,
            'usuario_id' => $admin->id,
            'proveedor_nombre' => $proveedor->nombre,
            'estado' => 'pendiente',
            'total' => 0,
        ]);

        $this->actingAs($admin)
            ->delete(route('proveedores.destroy', $proveedor))
            ->assertSessionHas('error')
            ->assertRedirect();

        $this->assertDatabaseHas('proveedores', ['id' => $proveedor->id]);
    }

    public function test_purchase_history_is_available_in_inventory(): void
    {
        $admin = $this->createAdmin();
        $proveedor = Proveedor::create(['nombre' => 'Proveedor con compras']);
        Compra::create([
            'proveedor_id' => $proveedor->id,
            'usuario_id' => $admin->id,
            'proveedor_nombre' => $proveedor->nombre,
            'estado' => 'pendiente',
            'total' => 18,
        ]);

        $this->actingAs($admin)
            ->get(route('inventario.index', ['tab' => 'compras']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Inventario/Index')
                ->where('compras.total', 1)
                ->where('compras.data.0.proveedor_nombre', 'Proveedor con compras')
                ->has('proveedoresActivos', 1));
    }

    private function createAdmin(): Usuario
    {
        $usuario = Usuario::factory()->create();
        $rol = Rol::create([
            'nombre' => 'admin',
            'nombre_visible' => 'Administrador',
        ]);
        $usuario->asignarRol($rol);

        return $usuario;
    }
}
