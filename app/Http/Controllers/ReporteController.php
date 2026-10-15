<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Caja;
use App\Models\Compra;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\Venta;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use ZipArchive;

class ReporteController extends Controller
{
    private const REPORTES = ['ventas', 'inventario', 'compras', 'caja', 'domicilios'];

    public function index(Request $request): Response
    {
        $inicioMes = now()->startOfMonth()->toDateString();
        $finMes = now()->endOfMonth()->toDateString();

        $filtros = $request->validate([
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'reporte' => ['nullable', Rule::in(self::REPORTES)],
        ]);

        $desde = $filtros['desde'] ?? $inicioMes;
        $hasta = $filtros['hasta'] ?? ($filtros['desde'] ?? $finMes);
        $reporte = $filtros['reporte'] ?? 'ventas';
        $fechaInicio = $desde.' 00:00:00';
        $fechaFin = $hasta.' 23:59:59.999999';

        [$resumen, $registros] = match ($reporte) {
            'inventario' => $this->reporteInventario($fechaInicio, $fechaFin, true),
            'compras' => $this->reporteCompras($fechaInicio, $fechaFin, true),
            'caja' => $this->reporteCaja($fechaInicio, $fechaFin, true),
            'domicilios' => $this->reporteDomicilios($fechaInicio, $fechaFin, true),
            default => $this->reporteVentas($fechaInicio, $fechaFin, true),
        };

        return Inertia::render('Reportes/Index', [
            'reporteActual' => $reporte,
            'filtros' => [
                'desde' => $desde,
                'hasta' => $hasta,
            ],
            'resumen' => $resumen,
            'registros' => $registros,
        ]);
    }

    public function export(Request $request, string $formato)
    {
        abort_unless(in_array($formato, ['pdf', 'excel'], true), 404);

        $filtros = $request->validate([
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'reporte' => ['nullable', Rule::in(self::REPORTES)],
        ]);

        $desde = $filtros['desde'] ?? now()->startOfMonth()->toDateString();
        $hasta = $filtros['hasta'] ?? ($filtros['desde'] ?? now()->endOfMonth()->toDateString());
        $reporte = $filtros['reporte'] ?? 'ventas';
        $inicio = $desde.' 00:00:00';
        $fin = $hasta.' 23:59:59.999999';

        [$resumen, $registros] = match ($reporte) {
            'inventario' => $this->reporteInventario($inicio, $fin, false),
            'compras' => $this->reporteCompras($inicio, $fin, false),
            'caja' => $this->reporteCaja($inicio, $fin, false),
            'domicilios' => $this->reporteDomicilios($inicio, $fin, false),
            default => $this->reporteVentas($inicio, $fin, false),
        };

        $nombreReporte = ucfirst($reporte);
        $nombreArchivo = 'reporte-'.$reporte.'-'.$desde.'-'.$hasta;
        $columnas = $this->columnas($reporte);
        $filas = $registros->all();

        if ($formato === 'pdf') {
            return Pdf::loadView('reportes.pdf', [
                'titulo' => 'Reporte de '.$nombreReporte,
                'reporte' => $reporte,
                'desde' => $desde,
                'hasta' => $hasta,
                'resumen' => $resumen,
                'columnas' => $columnas,
                'filas' => $filas,
            ])
                ->setPaper('a4', 'landscape')
                ->download($nombreArchivo.'.pdf');
        }

        return $this->descargarExcel($nombreArchivo, $nombreReporte, $desde, $hasta, $resumen, $columnas, $filas);
    }

    private function reporteVentas(string $inicio, string $fin, bool $paginar): array
    {
        $consulta = Venta::query()->whereBetween('created_at', [$inicio, $fin]);
        $resumen = (clone $consulta)
            ->selectRaw('COUNT(*) as cantidad, COALESCE(SUM(total), 0) as total, COALESCE(SUM(costo_domicilio), 0) as domicilios')
            ->first();

        $registros = $this->mapearRegistros($consulta->latest(), $paginar, fn (Venta $venta) => [
            'id' => $venta->id,
            'fecha' => $venta->created_at?->format('d/m/Y H:i'),
            'usuario' => $venta->vendedor_nombre,
            'tipo' => $venta->tipo_venta === 'domicilio' ? 'Domicilio' : 'Mostrador',
            'estado' => $venta->domicilio_estado,
            'costo_adicional' => (float) $venta->costo_domicilio,
            'total' => (float) $venta->total,
        ]);

        return [[
            ['label' => 'Ventas registradas', 'value' => (int) $resumen->cantidad, 'format' => 'number'],
            ['label' => 'Total vendido', 'value' => (float) $resumen->total, 'format' => 'currency'],
            ['label' => 'Costos de domicilios', 'value' => (float) $resumen->domicilios, 'format' => 'currency'],
        ], $registros];
    }

    private function reporteInventario(string $inicio, string $fin, bool $paginar): array
    {
        $consulta = MovimientoInventario::query()->whereBetween('created_at', [$inicio, $fin]);
        $resumen = (clone $consulta)
            ->selectRaw('COUNT(*) as cantidad, COALESCE(SUM(CASE WHEN diferencia > 0 THEN diferencia ELSE 0 END), 0) as entradas, COALESCE(SUM(CASE WHEN diferencia < 0 THEN ABS(diferencia) ELSE 0 END), 0) as salidas')
            ->first();

        $productosBajoStock = Producto::query()
            ->where('activo', true)
            ->whereColumn('stock', '<=', 'stock_minimo')
            ->count();

        $registros = $this->mapearRegistros($consulta->latest(), $paginar, fn (MovimientoInventario $movimiento) => [
            'id' => $movimiento->id,
            'fecha' => $movimiento->created_at?->format('d/m/Y H:i'),
            'producto' => $movimiento->producto_nombre,
            'codigo' => $movimiento->producto_codigo,
            'tipo' => ucfirst($movimiento->tipo),
            'diferencia' => (int) $movimiento->diferencia,
            'stock_anterior' => (int) $movimiento->stock_anterior,
            'stock_nuevo' => (int) $movimiento->stock_nuevo,
            'motivo' => $movimiento->motivo,
            'usuario' => $movimiento->usuario_nombre,
        ]);

        return [[
            ['label' => 'Movimientos', 'value' => (int) $resumen->cantidad, 'format' => 'number'],
            ['label' => 'Unidades ingresadas', 'value' => (int) $resumen->entradas, 'format' => 'number'],
            ['label' => 'Unidades salidas', 'value' => (int) $resumen->salidas, 'format' => 'number'],
            ['label' => 'Productos en stock mínimo', 'value' => $productosBajoStock, 'format' => 'number'],
        ], $registros];
    }

    private function reporteCompras(string $inicio, string $fin, bool $paginar): array
    {
        $consulta = Compra::query()->whereBetween('created_at', [$inicio, $fin]);
        $resumen = (clone $consulta)
            ->selectRaw("COUNT(*) as cantidad, COALESCE(SUM(total), 0) as total, SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes, SUM(CASE WHEN estado = 'recibida' THEN 1 ELSE 0 END) as recibidas")
            ->first();

        $registros = $this->mapearRegistros($consulta->latest(), $paginar, fn (Compra $compra) => [
            'id' => $compra->id,
            'fecha' => $compra->created_at?->format('d/m/Y H:i'),
            'proveedor' => $compra->proveedor_nombre,
            'estado' => ucfirst($compra->estado),
            'total' => (float) $compra->total,
            'recibida_en' => $compra->recibida_en?->format('d/m/Y H:i'),
        ]);

        return [[
            ['label' => 'Compras registradas', 'value' => (int) $resumen->cantidad, 'format' => 'number'],
            ['label' => 'Valor de compras', 'value' => (float) $resumen->total, 'format' => 'currency'],
            ['label' => 'Pendientes de recibir', 'value' => (int) $resumen->pendientes, 'format' => 'number'],
            ['label' => 'Compras recibidas', 'value' => (int) $resumen->recibidas, 'format' => 'number'],
        ], $registros];
    }

    private function reporteCaja(string $inicio, string $fin, bool $paginar): array
    {
        $consulta = Caja::query()
            ->where('estado', 'cerrada')
            ->whereBetween('cerrada_en', [$inicio, $fin]);
        $resumen = (clone $consulta)
            ->selectRaw('COUNT(*) as cantidad, COALESCE(SUM(monto_esperado), 0) as esperado, COALESCE(SUM(monto_contado), 0) as contado, COALESCE(SUM(diferencia), 0) as diferencia')
            ->first();

        $registros = $this->mapearRegistros($consulta->latest('cerrada_en'), $paginar, fn (Caja $caja) => [
            'id' => $caja->id,
            'fecha' => $caja->cerrada_en?->format('d/m/Y H:i'),
            'usuario' => $caja->usuario_nombre,
            'monto_inicial' => (float) $caja->monto_inicial,
            'monto_esperado' => (float) $caja->monto_esperado,
            'monto_contado' => (float) $caja->monto_contado,
            'diferencia' => (float) $caja->diferencia,
        ]);

        return [[
            ['label' => 'Cajas cerradas', 'value' => (int) $resumen->cantidad, 'format' => 'number'],
            ['label' => 'Efectivo esperado', 'value' => (float) $resumen->esperado, 'format' => 'currency'],
            ['label' => 'Efectivo contado', 'value' => (float) $resumen->contado, 'format' => 'currency'],
            ['label' => 'Diferencia acumulada', 'value' => (float) $resumen->diferencia, 'format' => 'currency'],
        ], $registros];
    }

    private function reporteDomicilios(string $inicio, string $fin, bool $paginar): array
    {
        $consulta = Venta::query()
            ->where('tipo_venta', 'domicilio')
            ->whereBetween('created_at', [$inicio, $fin]);
        $resumen = (clone $consulta)
            ->selectRaw("COUNT(*) as cantidad, COALESCE(SUM(total), 0) as total, COALESCE(SUM(costo_domicilio), 0) as costos, SUM(CASE WHEN domicilio_estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes, SUM(CASE WHEN domicilio_estado = 'entregado' THEN 1 ELSE 0 END) as entregados")
            ->first();

        $registros = $this->mapearRegistros($consulta->latest(), $paginar, fn (Venta $venta) => [
            'id' => $venta->id,
            'fecha' => $venta->created_at?->format('d/m/Y H:i'),
            'cliente' => $venta->cliente_nombre,
            'telefono' => $venta->cliente_telefono,
            'direccion' => $venta->domicilio_direccion,
            'estado' => ucfirst((string) $venta->domicilio_estado),
            'costo_domicilio' => (float) $venta->costo_domicilio,
            'total' => (float) $venta->total,
            'usuario' => $venta->vendedor_nombre,
        ]);

        return [[
            ['label' => 'Domicilios', 'value' => (int) $resumen->cantidad, 'format' => 'number'],
            ['label' => 'Ventas de domicilios', 'value' => (float) $resumen->total, 'format' => 'currency'],
            ['label' => 'Costos cobrados', 'value' => (float) $resumen->costos, 'format' => 'currency'],
            ['label' => 'Pendientes / entregados', 'value' => (int) $resumen->pendientes.' / '.(int) $resumen->entregados, 'format' => 'text'],
        ], $registros];
    }

    private function mapearRegistros(Builder $consulta, bool $paginar, callable $mapear): Collection|\Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        if ($paginar) {
            return $consulta->paginate(15)->withQueryString()->through($mapear);
        }

        return $consulta->get()->map($mapear);
    }

    private function columnas(string $reporte): array
    {
        return match ($reporte) {
            'inventario' => [
                'fecha' => 'Fecha', 'producto' => 'Producto', 'codigo' => 'Código',
                'tipo' => 'Tipo', 'diferencia' => 'Cambio', 'stock_anterior' => 'Stock anterior',
                'stock_nuevo' => 'Stock nuevo', 'motivo' => 'Motivo', 'usuario' => 'Usuario',
            ],
            'compras' => [
                'id' => 'Compra', 'fecha' => 'Fecha', 'proveedor' => 'Proveedor',
                'estado' => 'Estado', 'total' => 'Total', 'recibida_en' => 'Recibida',
            ],
            'caja' => [
                'id' => 'Caja', 'fecha' => 'Cierre', 'usuario' => 'Usuario',
                'monto_inicial' => 'Monto inicial', 'monto_esperado' => 'Esperado',
                'monto_contado' => 'Contado', 'diferencia' => 'Diferencia',
            ],
            'domicilios' => [
                'id' => 'Pedido', 'fecha' => 'Fecha', 'cliente' => 'Cliente',
                'telefono' => 'Teléfono', 'direccion' => 'Dirección', 'estado' => 'Estado',
                'costo_domicilio' => 'Costo domicilio', 'total' => 'Total', 'usuario' => 'Usuario',
            ],
            default => [
                'id' => 'Venta', 'fecha' => 'Fecha', 'usuario' => 'Usuario',
                'tipo' => 'Tipo', 'estado' => 'Estado domicilio',
                'costo_adicional' => 'Costo domicilio', 'total' => 'Total',
            ],
        };
    }

    private function descargarExcel(string $nombreArchivo, string $nombreReporte, string $desde, string $hasta, array $resumen, array $columnas, array $filas)
    {
        $archivoTemporal = tempnam(sys_get_temp_dir(), 'reporte_');
        $zip = new ZipArchive();

        if ($archivoTemporal === false) {
            throw new RuntimeException('No fue posible preparar el archivo Excel del reporte.');
        }

        $abierto = false;

        try {
            if ($zip->open($archivoTemporal, ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('No fue posible preparar el archivo Excel del reporte.');
            }
            $abierto = true;

            $archivos = [
                '[Content_Types].xml' => $this->xmlContentTypes(),
                '_rels/.rels' => $this->xmlRootRelationships(),
                'xl/workbook.xml' => $this->xmlWorkbook(),
                'xl/_rels/workbook.xml.rels' => $this->xmlWorkbookRelationships(),
                'xl/worksheets/sheet1.xml' => $this->xmlWorksheet($nombreReporte, $desde, $hasta, $resumen, $columnas, $filas),
            ];

            foreach ($archivos as $ruta => $contenido) {
                if (! $zip->addFromString($ruta, $contenido)) {
                    throw new RuntimeException('No fue posible completar el archivo Excel del reporte.');
                }
            }

            if (! $zip->close()) {
                $abierto = false;
                throw new RuntimeException('No fue posible finalizar el archivo Excel del reporte.');
            }
            $abierto = false;

            return response()->download($archivoTemporal, $nombreArchivo.'.xlsx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])->deleteFileAfterSend(true);
        } catch (\Throwable $exception) {
            if ($abierto) {
                $zip->close();
            }
            if (is_file($archivoTemporal)) {
                unlink($archivoTemporal);
            }

            throw $exception;
        }
    }

    private function xmlContentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'</Types>';
    }

    private function xmlRootRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private function xmlWorkbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="Reporte" sheetId="1" r:id="rId1"/></sheets></workbook>';
    }

    private function xmlWorkbookRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'</Relationships>';
    }

    private function xmlWorksheet(string $nombreReporte, string $desde, string $hasta, array $resumen, array $columnas, array $filas): string
    {
        $rows = [];
        $rows[] = ['Reporte de '.$nombreReporte];
        $rows[] = ['Rango de fechas', $desde.' al '.$hasta];
        foreach ($resumen as $dato) {
            $rows[] = [$dato['label'], $dato['value']];
        }
        $rows[] = [];
        $rows[] = array_values($columnas);

        foreach ($filas as $fila) {
            $rows[] = array_map(fn (string $key) => $fila[$key] ?? '', array_keys($columnas));
        }

        $sheetRows = '';
        foreach ($rows as $rowNumber => $cells) {
            $sheetRows .= '<row r="'.($rowNumber + 1).'">';
            foreach (array_values($cells) as $columnIndex => $value) {
                $reference = $this->columnaExcel($columnIndex + 1).($rowNumber + 1);
                if (is_int($value) || is_float($value)) {
                    $sheetRows .= '<c r="'.$reference.'"><v>'.$value.'</v></c>';
                } else {
                    $text = htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                    $sheetRows .= '<c r="'.$reference.'" t="inlineStr"><is><t xml:space="preserve">'.$text.'</t></is></c>';
                }
            }
            $sheetRows .= '</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$sheetRows.'</sheetData></worksheet>';
    }

    private function columnaExcel(int $numero): string
    {
        $letras = '';
        while ($numero > 0) {
            $resto = ($numero - 1) % 26;
            $letras = chr(65 + $resto).$letras;
            $numero = intdiv($numero - 1, 26);
        }

        return $letras;
    }
}
