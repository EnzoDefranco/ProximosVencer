<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Listado de artículos vencidos</title>
  <style>
    body { font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; font-size: 12px; }
    h1 { font-size: 18px; margin-bottom: 4px; }
    h2 { font-size: 14px; margin-top: 0; color: #555; }
    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    th, td { border: 1px solid #ddd; padding: 4px 6px; }
    th { background: #f3f4f6; text-align: left; }
    .right { text-align: right; }
    .mono { font-family: "SF Mono", ui-monospace, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }
    @media print {
      body { margin: 0.5cm; }
    }
  </style>
</head>
<body>
  <h1>Artículos vencidos</h1>
  <h2>
    Vencimiento entre
    {{ \Carbon\Carbon::parse($desde)->format('d/m/Y') }}
    y
    {{ \Carbon\Carbon::parse($hasta)->format('d/m/Y') }}
    @if($q)
      — Filtro: “{{ $q }}”
    @endif
  </h2>

  <table>
    <thead>
      <tr>
        <th>Artículo</th>
        <th>Descripción</th>
        <th>Vence</th>
        <th>Primer día vencido</th>
        <th class="right">Días vencido</th>
        <th class="right">Unidades</th>
        <th>Ubicación</th>
        <th>Contenedor</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($rows as $row)
        @php
          $vence      = \Carbon\Carbon::parse($row->fechaVencimiento);
          $primerDia  = \Carbon\Carbon::parse($row->fechaPrimerVencido);
          $diasVenc   = $primerDia->diffInDays(\Carbon\Carbon::today());
        @endphp
        <tr>
          <td class="mono">{{ $row->ArticuloCodigo }}</td>
          <td>{{ $row->ArticuloDescripcion }}</td>
          <td>{{ $vence->format('d/m/Y') }}</td>
          <td>{{ $primerDia->format('d/m/Y') }}</td>
          <td class="right">{{ $diasVenc }}</td>
          <td class="right">{{ number_format($row->unidadesPrimerVencido ?? 0, 0, ',', '.') }}</td>
          <td>{{ $row->UbicacionEjemplo }}</td>
          <td>{{ $row->ContenedorEjemplo }}</td>
        </tr>
      @empty
        <tr>
          <td colspan="8">No se encontraron artículos vencidos para los filtros indicados.</td>
        </tr>
      @endforelse
    </tbody>
  </table>
</body>
</html>
