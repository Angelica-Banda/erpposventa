<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Compra;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Rol;
use App\Models\Usuario;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ModuleIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_sale_delivery_cash_dashboard_and_reports_share_the_same_business_data(): void
    {
        $admin = $this->createUserWithRole('admin');
        $vendedor = $this->createUserWithRole('vendedor');
        $proveedor = Proveedor::create(['nombre' => 'Proveedor integrado']);
        $categoria = Categoria::create(['nombre' => 'Categoría integrada']);
        $producto = Producto::create([
            'categoria_id' => $categoria->id,
            'codigo' => 'INTEGRACION-001',
            'nombre' => 'Producto integrado',
            'precio_compra' => 7,
            'precio_venta' => 20,
            'stock' => 0,
            'stock_minimo' => 1,
            'activo' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('inventario.compras.store'), [
                'proveedor_id' => $proveedor->id,
                'detalles' => [[
                    'producto_id' => $producto->id,
                    'cantidad' => 6,
                    'precio_unitario' => 8,
                ]],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('inventario.index', ['tab' => 'compras']));

        $compra = Compra::firstOrFail();
        $this->post(route('inventario.compras.receive', $compra))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('inventario.index', ['tab' => 'compras']));

        $this->assertSame(6, $producto->fresh()->stock);
        $this->assertDatabaseHas('movimientos_inventario', [
            'producto_id' => $producto->id,
            'tipo' => 'compra',
            'diferencia' => 6,
        ]);

        $this->actingAs($vendedor)
            ->post(route('caja.open'), ['monto_inicial' => 100])
            ->assertSessionHasNoErrors();

        $this->post(route('ventas.store'), [
            'items' => [[
                'product_id' => $producto->id,
                'quantity' => 2,
            ]],
            'tipo_venta' => 'domicilio',
            'cliente_nombre' => 'Cliente integrado',
            'cliente_telefono' => '3001234567',
            'domicilio_direccion' => 'Calle de integración 123',
            'costo_domicilio' => 10,
        ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('receipt', fn (array $receipt) => $receipt['total'] === 50.0);

        $venta = Venta::firstOrFail();
        $this->assertSame(4, $producto->fresh()->stock);
        $this->assertSame(2, MovimientoInventario::where('producto_id', $producto->id)->count());

        $this->patch(route('domicilios.entregar', $venta))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');
        $this->assertSame('entregado', $venta->fresh()->domicilio_estado);

        $caja = \App\Models\Caja::where('usuario_id', $vendedor->id)->firstOrFail();
        $this->post(route('caja.close', $caja), ['monto_contado' => 150])
            ->assertSessionHasNoErrors();
        $this->assertSame('150.00', $caja->fresh()->monto_esperado);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('kpis.ventas.valor', 50)
                ->where('kpis.ventas.transacciones', 1)
                ->where('kpis.unidadesVendidas', 2)
                ->where('kpis.domicilios.pendientes', 0)
                ->where('kpis.domicilios.entregadosPeriodo', 1));

        $desde = now()->toDateString();
        $filtros = ['desde' => $desde, 'hasta' => $desde];

        $this->get(route('reportes.index', [...$filtros, 'reporte' => 'compras']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('registros.total', 1)
                ->where('registros.data.0.estado', 'Recibida'));

        $this->get(route('reportes.index', [...$filtros, 'reporte' => 'inventario']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('registros.total', 2)
                ->where('resumen.1.value', 6)
                ->where('resumen.2.value', 2));

        $this->get(route('reportes.index', [...$filtros, 'reporte' => 'caja']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('registros.total', 1)
                ->where('resumen.1.value', 150)
                ->where('resumen.2.value', 150));

        $this->get(route('reportes.index', [...$filtros, 'reporte' => 'domicilios']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('registros.total', 1)
                ->where('registros.data.0.estado', 'Entregado')
                ->where('resumen.3.value', '0 / 1'));
    }

    private function createUserWithRole(string $nombre): Usuario
    {
        $usuario = Usuario::factory()->create();
        $rol = Rol::firstOrCreate(
            ['nombre' => $nombre],
            ['nombre_visible' => ucfirst($nombre)],
        );
        $usuario->asignarRol($rol);

        return $usuario;
    }
}
