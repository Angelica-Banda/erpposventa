<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\DetalleVenta;
use App\Models\Producto;
use App\Models\Rol;
use App\Models\Usuario;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_real_sales_inventory_and_delivery_data_for_today(): void
    {
        $admin = $this->createUserWithRole('admin');
        $this->createSale($admin, 120, 2, 'mostrador', now());
        $this->createSale($admin, 50, 1, 'mostrador', now()->startOfWeek()->addDay()->addHours(8));
        $this->createSale($admin, 999, 9, 'mostrador', now()->startOfWeek()->subDay());
        $this->createSale($admin, 20, 1, 'domicilio', now()->subWeeks(2), 'pendiente');
        $this->createSale($admin, 30, 1, 'domicilio', now(), 'entregado');

        $categoria = Categoria::create(['nombre' => 'Alertas dashboard']);
        Producto::create([
            'categoria_id' => $categoria->id,
            'codigo' => 'DASH-LOW',
            'nombre' => 'Producto bajo',
            'precio_compra' => 10,
            'precio_venta' => 15,
            'stock' => 2,
            'stock_minimo' => 4,
            'activo' => true,
        ]);
        Producto::create([
            'categoria_id' => $categoria->id,
            'codigo' => 'DASH-INACTIVE',
            'nombre' => 'Producto inactivo',
            'stock' => 0,
            'stock_minimo' => 2,
            'activo' => false,
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('periodoActual', 'hoy')
                ->where('kpis.ventas.valor', 150)
                ->where('kpis.ventas.transacciones', 2)
                ->where('kpis.unidadesVendidas', 3)
                ->where('kpis.stockBajo.cantidad', 1)
                ->where('kpis.stockBajo.criticos', 0)
                ->where('kpis.domicilios.pendientes', 1)
                ->where('kpis.domicilios.entregadosPeriodo', 1)
                ->where('productosBajoStock.0.nombre', 'Producto bajo')
                ->where('productosBajoStock.0.categoria', 'Alertas dashboard')
                ->where('grafico.'.intdiv((int) now()->format('G'), 4).'.total', 150));
    }

    public function test_weekly_filter_aggregates_current_week_and_seller_keeps_existing_redirect(): void
    {
        $admin = $this->createUserWithRole('admin');
        $this->createSale($admin, 120, 2, 'mostrador', now());
        $this->createSale($admin, 50, 3, 'mostrador', now()->startOfWeek()->addDay()->addHours(8));
        $this->createSale($admin, 999, 9, 'mostrador', now()->startOfWeek()->subDay());

        $this->actingAs($admin)
            ->get(route('dashboard', ['periodo' => 'semana']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('periodoActual', 'semana')
                ->where('kpis.ventas.valor', 170)
                ->where('kpis.ventas.transacciones', 2)
                ->where('kpis.unidadesVendidas', 5)
                ->has('grafico', 7));

        $vendedor = $this->createUserWithRole('vendedor');
        $this->actingAs($vendedor)
            ->get(route('dashboard'))
            ->assertRedirect(route('ventas.index'));
    }

    public function test_authenticated_user_without_a_role_cannot_access_admin_dashboard(): void
    {
        $usuario = Usuario::factory()->create();

        $this->actingAs($usuario)
            ->get(route('dashboard'))
            ->assertForbidden();
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

    private function createSale(
        Usuario $usuario,
        float $total,
        int $cantidad,
        string $tipo,
        $fecha,
        ?string $estadoDomicilio = null,
    ): Venta {
        $venta = Venta::create([
            'usuario_id' => $usuario->id,
            'vendedor_nombre' => $usuario->name,
            'total' => $total,
            'tipo_venta' => $tipo,
            'domicilio_estado' => $estadoDomicilio,
        ]);

        DB::table('ventas')
            ->where('id', $venta->id)
            ->update(['created_at' => $fecha, 'updated_at' => $fecha]);

        DetalleVenta::create([
            'venta_id' => $venta->id,
            'producto_codigo' => 'DASH-PRODUCT',
            'producto_nombre' => 'Producto del dashboard',
            'cantidad' => $cantidad,
            'precio_unitario' => $total,
            'subtotal' => $total,
        ]);

        return $venta;
    }
}
