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
        h2 { color: #581c87; font-size: 13px; margin: 18px 0 8px; }
        h3 { color: #334155; font-size: 10px; margin: 12px 0 5px; }
        .analysis { background: #f8fafc; border: 1px solid #e2e8f0; margin: 10px 0; padding: 10px; }
        .analysis p { line-height: 1.5; margin: 4px 0; }
        .analysis ul { margin: 5px 0; padding-left: 18px; }
        .analysis li { line-height: 1.45; margin-bottom: 4px; }
        .chart { border-collapse: collapse; font-size: 8px; margin: 6px 0 12px; width: 100%; }
        .chart th { background: #ede9fe; color: #4c1d95; text-align: left; }
        .chart th, .chart td { border: 1px solid #ddd6fe; padding: 5px; }
        .bar-track { background: #ede9fe; height: 8px; width: 100%; }
        .bar { background: #7c3aed; height: 8px; }
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

    <h2>Análisis del período y recomendaciones</h2>
    <div class="analysis">
        <h3>Resumen ejecutivo</h3>
        <p>{{ $analisis['resumen_ejecutivo'] }}</p>
        @if ($analisis['comparacion']['disponible'])
            <p>Comparación con {{ $analisis['comparacion']['desde_anterior'] }} al {{ $analisis['comparacion']['hasta_anterior'] }}:
                @if ($analisis['comparacion']['porcentaje'] === null)
                    no es posible calcular la variación porcentual porque el valor anterior fue cero.
                @else
                    {{ number_format($analisis['comparacion']['porcentaje'], 1, ',', '.') }}% de variación.
                @endif
            </p>
        @else
            <p>No hay datos en el período anterior equivalente para calcular una comparación.</p>
        @endif
    </div>

    @forelse ($analisis['graficos'] as $grafico)
        <h3>{{ $grafico['titulo'] }}</h3>
        @php
            $valoresGrafico = collect($grafico['series'])->flatMap(fn ($serie) => $serie['valores']);
            $maximoGrafico = max(1, $valoresGrafico->map(fn ($valor) => abs((float) $valor))->max() ?? 0);
        @endphp
        <table class="chart">
            <thead>
                <tr><th>Período / producto</th>@foreach ($grafico['series'] as $serie)<th>{{ $serie['nombre'] }}</th>@endforeach</tr>
            </thead>
            <tbody>
                @foreach ($grafico['etiquetas'] as $indice => $etiqueta)
                    <tr>
                        <td>{{ $etiqueta }}</td>
                        @foreach ($grafico['series'] as $serie)
                            @php
                                $valor = (float) ($serie['valores'][$indice] ?? 0);
                                $ancho = abs($valor) > 0 ? max(2, abs($valor) / $maximoGrafico * 100) : 0;
                            @endphp
                            <td>
                                {{ $grafico['formato'] === 'currency' ? '$ '.number_format($valor, 0, ',', '.') : number_format($valor, 0, ',', '.') }}
                                <div class="bar-track"><div class="bar" style="width: {{ $ancho }}%"></div></div>
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @empty
        <div class="analysis">No hay información suficiente para generar gráficos en este período.</div>
    @endforelse

    @if ($analisis['detalle_productos'] !== [])
        <h3>Productos adquiridos</h3>
        <table class="chart">
            <thead><tr><th>Producto</th><th>Cantidad</th><th>Subtotal</th></tr></thead>
            <tbody>
                @foreach ($analisis['detalle_productos'] as $detalle)
                    <tr><td>{{ $detalle['producto'] }}</td><td>{{ number_format($detalle['cantidad'], 0, ',', '.') }}</td><td>$ {{ number_format($detalle['subtotal'], 0, ',', '.') }} COP</td></tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="analysis">
        <h3>Hallazgos relevantes</h3>
        <ul>@foreach ($analisis['hallazgos'] as $hallazgo)<li>{{ $hallazgo }}</li>@endforeach</ul>
        <h3>Conclusión</h3>
        <p>{{ $analisis['conclusion'] }}</p>
        <h3>Recomendaciones</h3>
        <ul>@foreach ($analisis['recomendaciones'] as $recomendacion)<li>{{ $recomendacion }}</li>@endforeach</ul>
    </div>

    <h2>Detalle del reporte</h2>
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
