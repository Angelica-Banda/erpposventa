<?php

namespace Tests\Feature;

use App\Models\Caja;
use App\Models\Categoria;
use App\Models\Compra;
use App\Models\DetalleCompra;
use App\Models\DetalleVenta;
use App\Models\Producto;
use App\Models\MovimientoInventario;
use App\Models\Proveedor;
use App\Models\Rol;
use App\Models\Usuario;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;
use ZipArchive;

class ReporteTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_filter_each_report_by_date_and_see_correct_summaries(): void
    {
        $admin = $this->createUserWithRole('admin');
        $desde = now()->toDateString();
        $hoy = now();
        $ayer = now()->subMonth();

        $venta = $this->createSale($admin, 38, 'domicilio', $hoy);
        $this->createSale($admin, 25, 'mostrador', $ayer);

        MovimientoInventario::create([
            'producto_codigo' => 'REP-001',
            'producto_nombre' => 'Producto del reporte',
            'tipo' => 'ajuste',
            'stock_anterior' => 4,
            'stock_nuevo' => 7,
            'diferencia' => 3,
            'motivo' => 'Ingreso de prueba',
            'usuario_id' => $admin->id,
            'usuario_nombre' => $admin->name,
            'created_at' => $hoy,
            'updated_at' => $hoy,
        ]);
        MovimientoInventario::create([
            'producto_codigo' => 'REP-002',
            'producto_nombre' => 'Producto antiguo',
            'tipo' => 'ajuste',
            'stock_anterior' => 5,
            'stock_nuevo' => 3,
            'diferencia' => -2,
            'motivo' => 'Movimiento anterior',
            'usuario_id' => $admin->id,
            'usuario_nombre' => $admin->name,
            'created_at' => $ayer,
            'updated_at' => $ayer,
        ]);
        DB::table('movimientos_inventario')
            ->where('producto_codigo', 'REP-002')
            ->update(['created_at' => $ayer, 'updated_at' => $ayer]);

        $proveedor = Proveedor::create(['nombre' => 'Proveedor reportes']);
        Compra::create([
            'proveedor_id' => $proveedor->id,
            'usuario_id' => $admin->id,
            'proveedor_nombre' => $proveedor->nombre,
            'estado' => 'recibida',
            'total' => 55,
            'recibida_en' => $hoy,
            'created_at' => $hoy,
            'updated_at' => $hoy,
        ]);
        Compra::create([
            'proveedor_id' => $proveedor->id,
            'usuario_id' => $admin->id,
            'proveedor_nombre' => $proveedor->nombre,
            'estado' => 'pendiente',
            'total' => 10,
            'created_at' => $ayer,
            'updated_at' => $ayer,
        ]);
        DB::table('compras')
            ->where('proveedor_nombre', $proveedor->nombre)
            ->where('estado', 'pendiente')
            ->update(['created_at' => $ayer, 'updated_at' => $ayer]);

        Caja::create([
            'usuario_id' => $admin->id,
            'usuario_nombre' => $admin->name,
            'monto_inicial' => 50,
            'monto_esperado' => 88,
            'monto_contado' => 85,
            'diferencia' => -3,
            'estado' => 'cerrada',
            'abierta_en' => $hoy->copy()->subHours(8),
            'cerrada_en' => $hoy,
        ]);

        $this->actingAs($admin);

        $this->get(route('reportes.index', ['reporte' => 'ventas', 'desde' => $desde, 'hasta' => $desde]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reportes/Index')
                ->where('reporteActual', 'ventas')
                ->where('registros.total', 1)
                ->where('registros.data.0.id', $venta->id)
                ->where('resumen.0.value', 1)
                ->where('resumen.1.value', 38));

        $this->get(route('reportes.index', ['reporte' => 'inventario', 'desde' => $desde, 'hasta' => $desde]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('registros.total', 1)
                ->where('resumen.0.value', 1)
                ->where('resumen.1.value', 3)
                ->where('resumen.2.value', 0));

        $this->get(route('reportes.index', ['reporte' => 'compras', 'desde' => $desde, 'hasta' => $desde]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('registros.total', 1)
                ->where('resumen.0.value', 1)
                ->where('resumen.1.value', 55)
                ->where('resumen.3.value', 1));

        $this->get(route('reportes.index', ['reporte' => 'caja', 'desde' => $desde, 'hasta' => $desde]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('registros.total', 1)
                ->where('resumen.0.value', 1)
                ->where('resumen.1.value', 88)
                ->where('resumen.2.value', 85)
                ->where('resumen.3.value', -3));

        $this->get(route('reportes.index', ['reporte' => 'domicilios', 'desde' => $desde, 'hasta' => $desde]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('registros.total', 1)
                ->where('registros.data.0.id', $venta->id)
                ->where('resumen.0.value', 1)
                ->where('resumen.1.value', 38)
                ->where('resumen.2.value', 8)
                ->where('resumen.3.value', '1 / 0'));
    }

    public function test_report_access_is_restricted_to_administrators(): void
    {
        $this->get(route('reportes.index'))
            ->assertRedirect(route('login'));

        $this->get(route('reportes.exportar', ['formato' => 'pdf']))
            ->assertRedirect(route('login'));

        $vendedor = $this->createUserWithRole('vendedor');
        $this->actingAs($vendedor)
            ->get(route('reportes.index'))
            ->assertRedirect(route('ventas.index'));

        $this->get(route('reportes.exportar', ['formato' => 'excel']))
            ->assertRedirect(route('ventas.index'));
    }

    public function test_report_defaults_to_current_month_and_rejects_reversed_dates(): void
    {
        $admin = $this->createUserWithRole('admin');

        $this->actingAs($admin)
            ->get(route('reportes.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filtros.desde', now()->startOfMonth()->toDateString())
                ->where('filtros.hasta', now()->endOfMonth()->toDateString())
                ->where('reporteActual', 'ventas'));

        $this->get(route('reportes.index', [
            'desde' => now()->toDateString(),
            'hasta' => now()->subDay()->toDateString(),
        ]))
            ->assertSessionHasErrors('hasta');
    }

    public function test_report_charts_use_real_data_for_each_report_and_respect_date_filters(): void
    {
        $admin = $this->createUserWithRole('admin');
        $hoy = now();
        $ayer = now()->subDay();
        $producto = Producto::create([
            'categoria_id' => Categoria::create(['nombre' => 'Categoría de reportes'])->id,
            'codigo' => 'CHART-001',
            'nombre' => 'Producto gráfico',
            'precio_compra' => 20,
            'precio_venta' => 35,
            'stock' => 2,
            'stock_minimo' => 5,
            'activo' => true,
        ]);
        $ventaActual = $this->createSale($admin, 70, 'mostrador', $hoy);
        DetalleVenta::create([
            'venta_id' => $ventaActual->id,
            'producto_id' => $producto->id,
            'producto_codigo' => $producto->codigo,
            'producto_nombre' => $producto->nombre,
            'cantidad' => 2,
            'precio_unitario' => 35,
            'subtotal' => 70,
        ]);
        $this->createSale($admin, 300, 'mostrador', $ayer);
        $proveedor = Proveedor::create(['nombre' => 'Proveedor gráfico']);
        $compra = Compra::create([
            'proveedor_id' => $proveedor->id,
            'usuario_id' => $admin->id,
            'proveedor_nombre' => $proveedor->nombre,
            'estado' => 'pendiente',
            'total' => 90,
            'created_at' => $hoy,
            'updated_at' => $hoy,
        ]);
        DetalleCompra::create([
            'compra_id' => $compra->id,
            'producto_id' => $producto->id,
            'producto_codigo' => $producto->codigo,
            'producto_nombre' => $producto->nombre,
            'cantidad' => 3,
            'precio_unitario' => 30,
            'subtotal' => 90,
        ]);
        Caja::create([
            'usuario_id' => $admin->id,
            'usuario_nombre' => $admin->name,
            'monto_inicial' => 20,
            'monto_esperado' => 100,
            'monto_contado' => 95,
            'diferencia' => -5,
            'estado' => 'cerrada',
            'abierta_en' => $hoy->copy()->subHours(8),
            'cerrada_en' => $hoy,
        ]);
        $this->createSale($admin, 40, 'domicilio', $hoy);

        $this->actingAs($admin);
        $filtros = ['desde' => $hoy->toDateString(), 'hasta' => $hoy->toDateString()];

        $this->get(route('reportes.index', [...$filtros, 'reporte' => 'ventas']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('analisis.graficos.0.titulo', 'Evolución de ventas')
                ->where('analisis.graficos.0.series.0.valores.0', 110)
                ->where('analisis.graficos.1.series.0.valores.0', 2)
                ->where('analisis.graficos.2.series.0.valores.0', 1)
                ->where('analisis.graficos.2.series.0.valores.1', 1)
                ->where('analisis.comparacion.disponible', true)
                ->where('analisis.comparacion.anterior', 300));

        $this->get(route('reportes.index', [...$filtros, 'reporte' => 'inventario']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('analisis.graficos.0.titulo', 'Stock actual bajo o mínimo (stock actual y mínimo configurado)')
                ->where('analisis.graficos.0.series.0.valores.0', 2)
                ->where('analisis.graficos.1.series.0.valores.0', 2));

        $this->get(route('reportes.index', [...$filtros, 'reporte' => 'compras']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('analisis.graficos.0.titulo', 'Evolución de compras')
                ->where('analisis.graficos.0.series.0.valores.0', 90)
                ->where('analisis.graficos.1.etiquetas.0', $proveedor->nombre)
                ->where('analisis.detalle_productos.0.cantidad', 3));

        $this->get(route('reportes.index', [...$filtros, 'reporte' => 'caja']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('analisis.graficos.0.series.0.valores.0', 100)
                ->where('analisis.graficos.0.series.1.valores.0', 95)
                ->where('analisis.graficos.0.series.2.valores.0', -5));

        $this->get(route('reportes.index', [...$filtros, 'reporte' => 'domicilios']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('analisis.graficos.0.etiquetas.0', 'Pendiente')
                ->where('analisis.graficos.0.series.0.valores.0', 1)
                ->where('analisis.graficos.1.series.1.valores.0', 1));
    }

    public function test_reports_show_no_data_when_a_period_has_no_chartable_records(): void
    {
        $admin = $this->createUserWithRole('admin');
        $fecha = now()->subYears(3)->toDateString();

        $this->actingAs($admin)
            ->get(route('reportes.index', ['reporte' => 'ventas', 'desde' => $fecha, 'hasta' => $fecha]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('analisis.graficos', [])
                ->where('analisis.comparacion.disponible', false));
    }

    public function test_admin_can_download_pdf_and_complete_excel_for_selected_report_and_dates(): void
    {
        $admin = $this->createUserWithRole('admin');
        $desde = now()->toDateString();
        $hasta = now()->toDateString();
        $this->actingAs($admin);

        for ($index = 1; $index <= 16; $index++) {
            $ultimoRegistro = $this->createSale($admin, $index * 10, 'mostrador', now());
        }

        $this->createSale($admin, 999, 'mostrador', now()->subMonth());

        $filtros = [
            'formato' => 'pdf',
            'reporte' => 'ventas',
            'desde' => $desde,
            'hasta' => $hasta,
        ];

        $this->get(route('reportes.exportar', $filtros))
            ->assertOk()
            ->assertDownload("reporte-ventas-{$desde}-{$hasta}.pdf")
            ->assertHeader('content-type', 'application/pdf');

        $filtros['formato'] = 'excel';
        $excel = $this->get(route('reportes.exportar', $filtros))
            ->assertOk()
            ->assertDownload("reporte-ventas-{$desde}-{$hasta}.xlsx")
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $archivoTemporal = $excel->baseResponse->getFile()->getPathname();
        $zip = new ZipArchive();

        try {
            $this->assertSame(true, $zip->open($archivoTemporal));
            $contenido = $zip->getFromName('xl/worksheets/sheet1.xml');
            $this->assertIsString($contenido);
            $this->assertSame(23, substr_count($contenido, '<row r='));
            $this->assertStringContainsString('s="1"', $contenido);
            $analisisExcel = $zip->getFromName('xl/worksheets/sheet2.xml');
            $graficosExcel = $zip->getFromName('xl/worksheets/sheet3.xml');
            $this->assertIsString($analisisExcel);
            $this->assertIsString($graficosExcel);
            $this->assertStringContainsString('Resumen ejecutivo', $analisisExcel);
            $this->assertStringContainsString('Evolución de ventas', $graficosExcel);
            $this->assertIsString($zip->getFromName('xl/styles.xml'));
            $this->assertStringContainsString((string) $ultimoRegistro->id, $contenido);
            $this->assertStringNotContainsString('999', $contenido);
        } finally {
            $zip->close();
            if (is_file($archivoTemporal)) {
                unlink($archivoTemporal);
            }
        }

        foreach (['inventario', 'compras', 'caja', 'domicilios'] as $reporte) {
            foreach (['pdf', 'excel'] as $formato) {
                $respuesta = $this->get(route('reportes.exportar', [
                    'formato' => $formato,
                    'reporte' => $reporte,
                    'desde' => $desde,
                    'hasta' => $hasta,
                ]));

                if ($formato === 'excel') {
                    $rutaTemporal = $respuesta->baseResponse->getFile()->getPathname();
                    try {
                        $respuesta->assertOk()
                            ->assertDownload("reporte-{$reporte}-{$desde}-{$hasta}.xlsx");
                    } finally {
                        if (is_file($rutaTemporal)) {
                            unlink($rutaTemporal);
                        }
                    }
                } else {
                    $respuesta->assertOk()
                        ->assertDownload("reporte-{$reporte}-{$desde}-{$hasta}.pdf");
                }
            }
        }
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

    private function createSale(Usuario $usuario, float $total, string $tipo, $fecha): Venta
    {
        $venta = Venta::create([
            'usuario_id' => $usuario->id,
            'vendedor_nombre' => $usuario->name,
            'total' => $total,
            'tipo_venta' => $tipo,
            'cliente_nombre' => $tipo === 'domicilio' ? 'Cliente del reporte' : null,
            'cliente_telefono' => $tipo === 'domicilio' ? '3001234567' : null,
            'domicilio_direccion' => $tipo === 'domicilio' ? 'Calle 1 # 2-3' : null,
            'costo_domicilio' => $tipo === 'domicilio' ? 8 : 0,
            'domicilio_estado' => $tipo === 'domicilio' ? 'pendiente' : null,
        ]);

        DB::table('ventas')
            ->where('id', $venta->id)
            ->update(['created_at' => $fecha, 'updated_at' => $fecha]);

        return $venta;
    }
}
