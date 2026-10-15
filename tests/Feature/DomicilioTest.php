<?php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\Usuario;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DomicilioTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_and_vendor_only_see_their_own_deliveries(): void
    {
        $vendedor = $this->createUserWithRole('vendedor');
        $admin = $this->createUserWithRole('admin');
        $ventaVendedor = $this->createDelivery($vendedor, 'Cliente vendedor');
        $ventaAdmin = $this->createDelivery($admin, 'Cliente admin');

        $this->actingAs($vendedor)
            ->get(route('domicilios.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Domicilios/Index')
                ->where('domicilios.total', 1)
                ->where('domicilios.data.0.id', $ventaVendedor->id)
                ->where('domicilios.data.0.cliente_nombre', 'Cliente vendedor'));

        $this->actingAs($admin)
            ->get(route('domicilios.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('domicilios.total', 1)
                ->where('domicilios.data.0.id', $ventaAdmin->id)
                ->where('domicilios.data.0.cliente_nombre', 'Cliente admin'));
    }

    public function test_owner_can_mark_pending_delivery_as_delivered_once(): void
    {
        $usuario = $this->createUserWithRole('vendedor');
        $venta = $this->createDelivery($usuario);

        $this->actingAs($usuario)
            ->from(route('domicilios.index'))
            ->patch(route('domicilios.entregar', $venta))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Domicilio #'.$venta->id.' marcado como entregado.')
            ->assertRedirect(route('domicilios.index'));

        $this->assertSame('entregado', $venta->fresh()->domicilio_estado);

        $this->from(route('domicilios.index'))
            ->patch(route('domicilios.entregar', $venta))
            ->assertSessionHasErrors('domicilio_estado')
            ->assertRedirect(route('domicilios.index'));

        $this->assertSame('entregado', $venta->fresh()->domicilio_estado);
    }

    public function test_user_cannot_view_or_update_another_users_delivery(): void
    {
        $owner = $this->createUserWithRole('vendedor');
        $otherUser = $this->createUserWithRole('admin');
        $venta = $this->createDelivery($owner);

        $this->actingAs($otherUser)
            ->get(route('domicilios.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('domicilios.total', 0));

        $this->patch(route('domicilios.entregar', $venta))
            ->assertForbidden();

        $this->assertSame('pendiente', $venta->fresh()->domicilio_estado);
    }

    public function test_delivery_filters_return_only_matching_owned_orders(): void
    {
        $usuario = $this->createUserWithRole('vendedor');
        $pending = $this->createDelivery($usuario, 'Cliente Pendiente', 'pendiente');
        $delivered = $this->createDelivery($usuario, 'Cliente Entregado', 'entregado');

        $this->actingAs($usuario)
            ->get(route('domicilios.index', ['busqueda' => 'Pendiente', 'estado' => 'pendiente']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('domicilios.total', 1)
                ->where('domicilios.data.0.id', $pending->id)
                ->where('filters.busqueda', 'Pendiente')
                ->where('filters.estado', 'pendiente'));

        $this->get(route('domicilios.index', ['estado' => 'entregado']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('domicilios.total', 1)
                ->where('domicilios.data.0.id', $delivered->id));
    }

    private function createUserWithRole(string $roleName): Usuario
    {
        $usuario = Usuario::factory()->create();
        $rol = Rol::firstOrCreate(
            ['nombre' => $roleName],
            ['nombre_visible' => ucfirst($roleName)],
        );
        $usuario->asignarRol($rol);

        return $usuario;
    }

    private function createDelivery(Usuario $usuario, string $customerName = 'Cliente de prueba', string $status = 'pendiente'): Venta
    {
        return Venta::create([
            'usuario_id' => $usuario->id,
            'vendedor_nombre' => $usuario->name,
            'total' => 35,
            'tipo_venta' => 'domicilio',
            'cliente_nombre' => $customerName,
            'cliente_telefono' => '3001234567',
            'domicilio_direccion' => 'Carrera 1 # 2-3',
            'costo_domicilio' => 5,
            'domicilio_estado' => $status,
        ]);
    }
}
