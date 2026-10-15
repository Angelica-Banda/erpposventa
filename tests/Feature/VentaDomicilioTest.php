<?php

namespace Tests\Feature;

use App\Models\Caja;
use App\Models\Categoria;
use App\Models\Producto;
use App\Models\Rol;
use App\Models\Usuario;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VentaDomicilioTest extends TestCase
{
    use RefreshDatabase;

    public function test_domicile_sale_saves_customer_details_adds_delivery_cost_to_sale_and_cash_total(): void
    {
        $usuario = $this->createUserWithRole();
        $caja = $this->openCashRegister($usuario);
        $producto = $this->createProduct();

        $this->actingAs($usuario)
            ->post(route('ventas.store'), [
                'items' => [[
                    'product_id' => $producto->id,
                    'quantity' => 2,
                ]],
                'tipo_venta' => 'domicilio',
                'cliente_nombre' => 'Camila Cliente',
                'cliente_telefono' => '3001234567',
                'domicilio_direccion' => 'Calle 10 # 20-30, Apto 2',
                'costo_domicilio' => 8,
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('receipt', fn (array $receipt) => $receipt['tipo_venta'] === 'domicilio'
                && $receipt['cliente_nombre'] === 'Camila Cliente'
                && $receipt['costo_domicilio'] === 8.0
                && $receipt['total'] === 38.0
                && $receipt['domicilio_estado'] === 'pendiente');

        $venta = Venta::with('detalles')->firstOrFail();

        $this->assertSame('domicilio', $venta->tipo_venta);
        $this->assertSame('Camila Cliente', $venta->cliente_nombre);
        $this->assertSame('3001234567', $venta->cliente_telefono);
        $this->assertSame('Calle 10 # 20-30, Apto 2', $venta->domicilio_direccion);
        $this->assertSame('8.00', $venta->costo_domicilio);
        $this->assertSame('pendiente', $venta->domicilio_estado);
        $this->assertSame('38.00', $venta->total);
        $this->assertSame(3, $producto->fresh()->stock);

        $this->post(route('caja.close', $caja), ['monto_contado' => 88])
            ->assertSessionHasNoErrors();

        $this->assertSame('88.00', $caja->fresh()->monto_esperado);
    }

    public function test_counter_sale_keeps_its_existing_total_and_has_no_delivery_details(): void
    {
        $usuario = $this->createUserWithRole();
        $this->openCashRegister($usuario);
        $producto = $this->createProduct();

        $this->actingAs($usuario)
            ->post(route('ventas.store'), [
                'items' => [[
                    'product_id' => $producto->id,
                    'quantity' => 2,
                ]],
            ])
            ->assertSessionHasNoErrors();

        $venta = Venta::firstOrFail();

        $this->assertSame('mostrador', $venta->tipo_venta);
        $this->assertNull($venta->cliente_nombre);
        $this->assertNull($venta->cliente_telefono);
        $this->assertNull($venta->domicilio_direccion);
        $this->assertSame('0.00', $venta->costo_domicilio);
        $this->assertNull($venta->domicilio_estado);
        $this->assertSame('30.00', $venta->total);
    }

    public function test_domicile_sale_requires_customer_and_delivery_information_without_creating_sale(): void
    {
        $usuario = $this->createUserWithRole();
        $this->openCashRegister($usuario);
        $producto = $this->createProduct();

        $this->actingAs($usuario)
            ->from(route('ventas.index'))
            ->post(route('ventas.store'), [
                'items' => [[
                    'product_id' => $producto->id,
                    'quantity' => 2,
                ]],
                'tipo_venta' => 'domicilio',
                'costo_domicilio' => 8,
            ])
            ->assertSessionHasErrors([
                'cliente_nombre',
                'cliente_telefono',
                'domicilio_direccion',
            ])
            ->assertRedirect(route('ventas.index'));

        $this->assertDatabaseCount('ventas', 0);
        $this->assertSame(5, $producto->fresh()->stock);
    }

    private function createUserWithRole(): Usuario
    {
        $usuario = Usuario::factory()->create();
        $rol = Rol::firstOrCreate(
            ['nombre' => 'vendedor'],
            ['nombre_visible' => 'Vendedor'],
        );
        $usuario->asignarRol($rol);

        return $usuario;
    }

    private function openCashRegister(Usuario $usuario): Caja
    {
        $caja = Caja::create([
            'usuario_id' => $usuario->id,
            'usuario_nombre' => $usuario->name,
            'monto_inicial' => 50,
            'estado' => 'abierta',
            'abierta_en' => now(),
        ]);

        return $caja;
    }

    private function createProduct(): Producto
    {
        $categoria = Categoria::create(['nombre' => 'Categoría de domicilio']);

        return Producto::create([
            'categoria_id' => $categoria->id,
            'codigo' => 'TEST-DOMICILIO-001',
            'nombre' => 'Producto a domicilio',
            'precio_compra' => 7,
            'precio_venta' => 15,
            'stock' => 5,
            'stock_minimo' => 1,
            'activo' => true,
        ]);
    }
}
