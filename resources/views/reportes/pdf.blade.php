<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $titulo }}</title>
    <style>
        @page { margin: 28px; }
        body { color: #1e293b; font-family: DejaVu Sans, sans-serif; font-size: 9px; }
        h1 { color: #581c87; font-size: 20px; margin: 0 0 6px; }
        .range { color: #64748b; font-size: 10px; margin-bottom: 18px; }
        .summary { margin-bottom: 18px; width: 100%; }
        .summary td { background: #f5f3ff; border: 1px solid #ddd6fe; padding: 8px; width: 25%; }
        .summary strong { color: #581c87; display: block; font-size: 8px; margin-bottom: 5px; text-transform: uppercase; }
        table.data { border-collapse: collapse; width: 100%; }
        .data th { background: #581c87; color: #fff; font-size: 8px; text-align: left; }
        .data th, .data td { border: 1px solid #e2e8f0; padding: 6px; vertical-align: top; }
        .data tr:nth-child(even) td { background: #f8fafc; }
        .footer { color: #64748b; font-size: 8px; margin-top: 14px; text-align: right; }
    </style>
</head>
<body>
    <h1>{{ $titulo }}</h1>
    <div class="range">Periodo: {{ $desde }} al {{ $hasta }}</div>

    <table class="summary">
        <tr>
            @foreach ($resumen as $dato)
                <td>
                    <strong>{{ $dato['label'] }}</strong>
                    @if ($dato['format'] === 'currency')
                        $ {{ number_format((float) $dato['value'], 0, ',', '.') }} COP
                    @else
                        {{ $dato['value'] }}
                    @endif
                </td>
            @endforeach
        </tr>
    </table>

    <table class="data">
        <thead>
            <tr>
                @foreach ($columnas as $etiqueta)
                    <th>{{ $etiqueta }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($filas as $fila)
                <tr>
                    @foreach (array_keys($columnas) as $clave)
                        <td>
                            @if (in_array($clave, ['total', 'costo_adicional', 'costo_domicilio', 'monto_inicial', 'monto_esperado', 'monto_contado'], true) || ($clave === 'diferencia' && $reporte === 'caja'))
                                $ {{ number_format((float) ($fila[$clave] ?? 0), 0, ',', '.') }}
                            @else
                                {{ $fila[$clave] ?? '—' }}
                            @endif
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($columnas) }}">No hay registros en este periodo.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
    <div class="footer">Generado {{ now()->format('d/m/Y H:i') }}</div>
</body>
</html>
