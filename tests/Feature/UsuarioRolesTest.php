<?php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UsuarioRolesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_change_a_vendors_role(): void
    {
        $admin = $this->createUserWithRole('admin');
        $vendedor = $this->createUserWithRole('vendedor');
        $rolVendedor = Rol::where('nombre', 'vendedor')->firstOrFail();
        $rolAdmin = Rol::where('nombre', 'admin')->firstOrFail();

        $this->actingAs($admin)
            ->put(route('configuracion.usuarios.update', $vendedor), [
                'name' => $vendedor->name,
                'email' => $vendedor->email,
                'password' => '',
                'roles' => [$rolAdmin->id],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame(['admin'], $vendedor->fresh()->roles()->pluck('nombre')->all());

        $this->put(route('configuracion.usuarios.update', $vendedor), [
            'name' => $vendedor->name,
            'email' => $vendedor->email,
            'password' => '',
            'roles' => [$rolVendedor->id],
        ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame(['vendedor'], $vendedor->fresh()->roles()->pluck('nombre')->all());
        $this->assertSame(['admin'], $admin->fresh()->roles()->pluck('nombre')->all());
    }

    public function test_admin_cannot_change_own_roles_even_with_a_direct_update_request(): void
    {
        $admin = $this->createUserWithRole('admin');
        $rolVendedor = Rol::firstOrCreate(
            ['nombre' => 'vendedor'],
            ['nombre_visible' => 'Vendedor'],
        );

        $this->actingAs($admin)
            ->put(route('configuracion.usuarios.update', $admin), [
                'name' => 'Nombre que no debe guardarse',
                'email' => $admin->email,
                'password' => '',
                'roles' => [$rolVendedor->id],
            ])
            ->assertSessionHasErrors('roles');

        $this->assertSame('admin', $admin->fresh()->roles()->value('nombre'));
        $this->assertNotSame('Nombre que no debe guardarse', $admin->fresh()->name);
    }

    public function test_user_management_screen_marks_current_admin_and_protects_role_selection(): void
    {
        $admin = $this->createUserWithRole('admin');

        $this->actingAs($admin)
            ->get(route('configuracion.roles.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Configuracion/UsuariosRoles')
                ->where('currentUserId', $admin->id));
    }

    public function test_vendor_cannot_access_or_submit_admin_role_management_actions(): void
    {
        $vendedor = $this->createUserWithRole('vendedor');
        $otroVendedor = $this->createUserWithRole('vendedor');
        $rolAdmin = Rol::firstOrCreate(
            ['nombre' => 'admin'],
            ['nombre_visible' => 'Administrador'],
        );

        $this->actingAs($vendedor)
            ->get(route('configuracion.roles.index'))
            ->assertRedirect(route('ventas.index'));

        $this->post(route('configuracion.usuarios.store'), [
            'name' => 'Usuario elevado',
            'email' => 'elevado@example.test',
            'password' => 'password',
            'roles' => [$rolAdmin->id],
        ])
            ->assertRedirect(route('ventas.index'));

        $this->put(route('configuracion.usuarios.update', $otroVendedor), [
            'name' => $otroVendedor->name,
            'email' => $otroVendedor->email,
            'password' => '',
            'roles' => [$rolAdmin->id],
        ])
            ->assertRedirect(route('ventas.index'));

        $this->get(route('reportes.index'))
            ->assertRedirect(route('ventas.index'));

        $this->assertDatabaseMissing('usuarios', ['email' => 'elevado@example.test']);
        $this->assertSame(['vendedor'], $otroVendedor->fresh()->roles()->pluck('nombre')->all());
    }

    public function test_admin_cannot_delete_own_account_through_user_management(): void
    {
        $admin = $this->createUserWithRole('admin');

        $this->actingAs($admin)
            ->delete(route('configuracion.usuarios.destroy', $admin))
            ->assertRedirect()
            ->assertSessionHas('error', 'No puedes eliminar tu propio usuario de sesión.');

        $this->assertDatabaseHas('usuarios', ['id' => $admin->id]);
        $this->assertSame(['admin'], $admin->fresh()->roles()->pluck('nombre')->all());
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
