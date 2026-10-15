<?php

namespace Tests\Feature;

use App\Models\Caja;
use App\Models\Categoria;
use App\Models\Producto;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CajaTest extends TestCase
{
    use RefreshDatabase;

    public function test_open_sale_and_close_reconcile_cash_and_preserve_owner_history(): void
    {
        $usuario = $this->createUserWithRole();
        $producto = $this->createProduct();

        $this->actingAs($usuario)
            ->get(route('caja.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Caja/Index')
                ->where('cajaAbierta', null)
                ->where('historial.total', 0));

        $this->post(route('caja.open'), ['monto_inicial' => 50])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('caja.index'));

        $caja = Caja::firstOrFail();
        $this->assertSame('abierta', $caja->estado);

        $this->get(route('ventas.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('cajaAbierta', true));

        $this->post(route('ventas.store'), [
            'items' => [[
                'product_id' => $producto->id,
                'quantity' => 2,
            ]],
        ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('ventas.index'));

        $this->assertDatabaseHas('ventas', [
            'caja_id' => $caja->id,
            'usuario_id' => $usuario->id,
            'total' => 30,
        ]);
        $this->assertSame(3, $producto->fresh()->stock);

        $this->post(route('caja.close', $caja), ['monto_contado' => 75])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('caja.index'));

        $caja->refresh();
        $this->assertSame('cerrada', $caja->estado);
        $this->assertSame('80.00', $caja->monto_esperado);
        $this->assertSame('75.00', $caja->monto_contado);
        $this->assertSame('-5.00', $caja->diferencia);

        $this->get(route('caja.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('cajaAbierta', null)
                ->where('historial.total', 1)
                ->where('historial.data.0.ventas_total', 30));
    }

    public function test_sales_require_an_open_cash_register_and_duplicate_open_is_rejected(): void
    {
        $usuario = $this->createUserWithRole();
        $producto = $this->createProduct();

        $this->actingAs($usuario)
            ->from(route('ventas.index'))
            ->post(route('ventas.store'), [
                'items' => [[
                    'product_id' => $producto->id,
                    'quantity' => 2,
                ]],
            ])
            ->assertSessionHasErrors('caja')
            ->assertRedirect(route('ventas.index'));

        $this->assertDatabaseCount('ventas', 0);
        $this->assertSame(5, $producto->fresh()->stock);

        $this->post(route('caja.open'), ['monto_inicial' => 0])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseCount('cajas', 1);

        $this->from(route('caja.index'))
            ->post(route('caja.open'), ['monto_inicial' => 25])
            ->assertSessionHasErrors('caja')
            ->assertRedirect(route('caja.index'));

        $this->assertDatabaseCount('cajas', 1);
    }

    public function test_user_cannot_close_another_users_cash_register(): void
    {
        $owner = $this->createUserWithRole();
        $otherUser = $this->createUserWithRole();
        $caja = Caja::create([
            'usuario_id' => $owner->id,
            'usuario_nombre' => $owner->name,
            'monto_inicial' => 20,
            'estado' => 'abierta',
            'abierta_en' => now(),
        ]);

        $this->actingAs($otherUser)
            ->post(route('caja.close', $caja), ['monto_contado' => 20])
            ->assertForbidden();

        $this->assertSame('abierta', $caja->fresh()->estado);
        $this->assertNull($caja->fresh()->monto_contado);
    }

    public function test_closed_cash_cannot_be_used_for_sales_or_closed_again(): void
    {
        $usuario = $this->createUserWithRole();
        $producto = $this->createProduct();
        $this->actingAs($usuario);
        $this->post(route('caja.open'), ['monto_inicial' => 20])
            ->assertSessionHasNoErrors();

        $caja = Caja::firstOrFail();
        $this->post(route('caja.close', $caja), ['monto_contado' => 20])
            ->assertSessionHasNoErrors();

        $this->from(route('ventas.index'))
            ->post(route('ventas.store'), [
                'items' => [[
                    'product_id' => $producto->id,
                    'quantity' => 1,
                ]],
            ])
            ->assertSessionHasErrors('caja')
            ->assertRedirect(route('ventas.index'));

        $this->from(route('caja.index'))
            ->post(route('caja.close', $caja), ['monto_contado' => 20])
            ->assertSessionHasErrors('caja')
            ->assertRedirect(route('caja.index'));

        $this->assertSame(5, $producto->fresh()->stock);
        $this->assertDatabaseCount('ventas', 0);
        $this->assertSame('cerrada', $caja->fresh()->estado);
    }

    public function test_admin_can_open_a_cash_register_and_users_only_see_their_own(): void
    {
        $vendedor = $this->createUserWithRole();
        $admin = $this->createUserWithRole('admin');

        $this->actingAs($vendedor)
            ->post(route('caja.open'), ['monto_inicial' => 10])
            ->assertSessionHasNoErrors();
        $cajaVendedor = Caja::where('usuario_id', $vendedor->id)->firstOrFail();

        $this->actingAs($admin)
            ->post(route('caja.open'), ['monto_inicial' => 30])
            ->assertSessionHasNoErrors();
        $cajaAdmin = Caja::where('usuario_id', $admin->id)->firstOrFail();

        $this->get(route('caja.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('cajaAbierta.id', $cajaAdmin->id)
                ->where('historial.total', 0));

        $this->actingAs($vendedor)
            ->get(route('caja.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('cajaAbierta.id', $cajaVendedor->id)
                ->where('historial.total', 0));
    }

    public function test_admin_report_filters_cash_closures_and_calculates_totals_while_vendors_cannot_see_it(): void
    {
        $admin = $this->createUserWithRole('admin');
        $vendedor = $this->createUserWithRole();
        $today = now()->startOfDay();

        Caja::create([
            'usuario_id' => $vendedor->id,
            'usuario_nombre' => 'Ana Cajera',
            'monto_inicial' => 10,
            'monto_esperado' => 50,
            'monto_contado' => 48,
            'diferencia' => -2,
            'estado' => 'cerrada',
            'abierta_en' => $today->copy()->subHours(8),
            'cerrada_en' => $today->copy()->addHours(2),
        ]);
        Caja::create([
            'usuario_id' => $vendedor->id,
            'usuario_nombre' => 'Bruno Cajero',
            'monto_inicial' => 20,
            'monto_esperado' => 30,
            'monto_contado' => 30,
            'diferencia' => 0,
            'estado' => 'cerrada',
            'abierta_en' => $today->copy()->subDay()->subHours(8),
            'cerrada_en' => $today->copy()->subDay()->subHours(2),
        ]);

        $this->actingAs($admin)
            ->get(route('caja.index', [
                'usuario' => 'Ana',
                'desde' => $today->toDateString(),
                'hasta' => $today->toDateString(),
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('esAdministrador', true)
                ->where('reporteCierres.total', 1)
                ->where('reporteCierres.data.0.usuario_nombre', 'Ana Cajera')
                ->where('resumenCierres.total_cierres', 1)
                ->where('resumenCierres.monto_esperado', 50)
                ->where('resumenCierres.monto_contado', 48)
                ->where('resumenCierres.diferencia', -2)
                ->where('filtrosReporte.usuario', 'Ana'));

        $this->actingAs($vendedor)
            ->get(route('caja.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('esAdministrador', false)
                ->where('reporteCierres', null)
                ->where('resumenCierres', null));
    }

    private function createUserWithRole(string $roleName = 'vendedor'): Usuario
    {
        $usuario = Usuario::factory()->create();
        $rol = Rol::firstOrCreate(
            ['nombre' => $roleName],
            ['nombre_visible' => ucfirst($roleName)],
        );
        $usuario->asignarRol($rol);

        return $usuario;
    }

    private function createProduct(): Producto
    {
        $categoria = Categoria::create(['nombre' => 'Categoría de caja']);

        return Producto::create([
            'categoria_id' => $categoria->id,
            'codigo' => 'TEST-CAJA-001',
            'nombre' => 'Producto de caja',
            'precio_compra' => 7,
            'precio_venta' => 15,
            'stock' => 5,
            'stock_minimo' => 1,
            'activo' => true,
        ]);
    }
}
