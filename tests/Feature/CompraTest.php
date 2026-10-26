<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Compra;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CompraTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_and_last_purchase_cost_change_only_when_a_purchase_is_received(): void
    {
        $admin = $this->createUserWithRole('admin');
        $proveedor = Proveedor::create(['nombre' => 'Proveedor de prueba']);
        $producto = $this->createProduct();

        $this->actingAs($admin)
            ->post(route('inventario.compras.store'), [
                'proveedor_id' => $proveedor->id,
                'detalles' => [[
                    'producto_id' => $producto->id,
                    'cantidad' => 3,
                    'precio_unitario' => 12.5,
                ]],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('inventario.index', ['tab' => 'compras']));

        $compra = Compra::with('detalles')->firstOrFail();
        $producto->refresh();

        $this->assertSame('pendiente', $compra->estado);
        $this->assertSame(5, $producto->stock);
        $this->assertSame('7.00', $producto->precio_compra);
        $this->assertDatabaseCount('movimientos_inventario', 0);

        $this->post(route('inventario.compras.receive', $compra))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('inventario.index', ['tab' => 'compras']));

        $compra->refresh();
        $producto->refresh();

        $this->assertSame('recibida', $compra->estado);
        $this->assertNotNull($compra->recibida_en);
        $this->assertSame(8, $producto->stock);
        $this->assertSame('12.50', $producto->precio_compra);
        $this->assertDatabaseHas('movimientos_inventario', [
            'producto_id' => $producto->id,
            'tipo' => 'compra',
            'stock_anterior' => 5,
            'stock_nuevo' => 8,
            'diferencia' => 3,
        ]);

        $this->from(route('inventario.index', ['tab' => 'compras']))
            ->post(route('inventario.compras.receive', $compra))
            ->assertSessionHasErrors('compra')
            ->assertRedirect(route('inventario.index', ['tab' => 'compras']));

        $this->assertSame(8, $producto->fresh()->stock);
        $this->assertDatabaseCount('movimientos_inventario', 1);
    }

    public function test_vendedor_cannot_access_purchase_management(): void
    {
        $vendedor = $this->createUserWithRole('vendedor');

        $this->actingAs($vendedor)
            ->get(route('proveedores.index'))
            ->assertRedirect(route('ventas.index'));
    }

    public function test_admin_can_filter_and_paginate_purchase_history(): void
    {
        $admin = $this->createUserWithRole('admin');
        $proveedor = Proveedor::create(['nombre' => 'Proveedor de paginación']);
        $otroProveedor = Proveedor::create(['nombre' => 'Otro proveedor']);

        foreach (range(1, 17) as $number) {
            Compra::create([
                'proveedor_id' => $proveedor->id,
                'usuario_id' => $admin->id,
                'proveedor_nombre' => $proveedor->nombre,
                'estado' => 'pendiente',
                'total' => $number,
            ]);
        }

        Compra::create([
            'proveedor_id' => $proveedor->id,
            'usuario_id' => $admin->id,
            'proveedor_nombre' => $proveedor->nombre,
            'estado' => 'recibida',
            'total' => 25,
        ]);
        Compra::create([
            'proveedor_id' => $otroProveedor->id,
            'usuario_id' => $admin->id,
            'proveedor_nombre' => $otroProveedor->nombre,
            'estado' => 'pendiente',
            'total' => 30,
        ]);

        $filters = [
            'compra_busqueda' => 'Proveedor de paginación',
            'compra_estado' => 'pendiente',
            'compra_desde' => now()->toDateString(),
            'compra_hasta' => now()->toDateString(),
            'tab' => 'compras',
        ];

        $this->actingAs($admin)
            ->get(route('inventario.index', $filters))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Inventario/Index')
                ->where('compras.total', 17)
                ->where('compras.per_page', 15)
                ->where('compras.current_page', 1)
                ->has('compras.data', 15)
                ->where('comprasFilters.busqueda', 'Proveedor de paginación')
                ->where('comprasFilters.estado', 'pendiente'));

        $this->get(route('inventario.index', [...$filters, 'compras_page' => 2]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('compras.current_page', 2)
                ->has('compras.data', 2));
    }

    public function test_printable_receipt_is_only_available_for_a_received_purchase(): void
    {
        $admin = $this->createUserWithRole('admin');
        $proveedor = Proveedor::create(['nombre' => 'Proveedor del comprobante']);
        $compra = Compra::create([
            'proveedor_id' => $proveedor->id,
            'usuario_id' => $admin->id,
            'proveedor_nombre' => $proveedor->nombre,
            'estado' => 'pendiente',
            'total' => 25,
        ]);
        $compra->detalles()->create([
            'producto_codigo' => 'COMP-001',
            'producto_nombre' => 'Producto del comprobante',
            'cantidad' => 2,
            'precio_unitario' => 12.5,
            'subtotal' => 25,
        ]);

        $this->actingAs($admin)
            ->get(route('inventario.compras.receipt', $compra))
            ->assertNotFound();

        $compra->update([
            'estado' => 'recibida',
            'recibida_en' => now(),
        ]);

        $this->get(route('inventario.compras.receipt', $compra))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Proveedores/Comprobante')
                ->where('compra.id', $compra->id)
                ->where('compra.proveedor_nombre', $proveedor->nombre)
                ->where('compra.total', 25)
                ->where('compra.detalles.0.producto_codigo', 'COMP-001')
                ->where('compra.detalles.0.subtotal', 25));
    }

    private function createUserWithRole(string $roleName): Usuario
    {
        $usuario = Usuario::factory()->create();
        $rol = Rol::create([
            'nombre' => $roleName,
            'nombre_visible' => ucfirst($roleName),
        ]);
        $usuario->asignarRol($rol);

        return $usuario;
    }

    private function createProduct(): Producto
    {
        $categoria = Categoria::create(['nombre' => 'Categoría de prueba']);

        return Producto::create([
            'categoria_id' => $categoria->id,
            'codigo' => 'TEST-COMPRA-001',
            'nombre' => 'Producto de prueba',
            'precio_compra' => 7,
            'precio_venta' => 15,
            'stock' => 5,
            'stock_minimo' => 1,
            'activo' => true,
        ]);
    }
}
