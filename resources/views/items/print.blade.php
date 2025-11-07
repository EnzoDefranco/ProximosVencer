{{-- resources/views/items/print.blade.php --}}
<!doctype html><meta charset="utf-8">
<style>
  table{width:100%;border-collapse:collapse;font:12px system-ui,Segoe UI,Roboto,Arial}
  th,td{border:1px solid #e5e7eb;padding:6px 8px}
  th{background:#f3f4f6;text-align:left}
  .num{text-align:right}
</style>

<table>
  <thead>
    <tr>
      <th>Artículo</th>
      <th>Descripción</th>
      <th>Vence</th>
      <th class="num">Unidades</th>
      <th class="num">Días</th>
      <th>Ubicación</th>
      <th>Contenedor</th>
    </tr>
  </thead>
  <tbody>
    @forelse($rows as $r)
      <tr>
        <td style="font-family:monospace">{{ $r->ArticuloCodigo }}</td>
        <td>{{ $r->ArticuloDescripcion }}</td>
        <td>{{ \Carbon\Carbon::parse($r->fechaVencimiento)->format('d/m/Y') }}</td>
        <td class="num">{{ number_format($r->Unidades ?? 0, 0, ',', '.') }}</td>
        <td class="num">{{ $r->diasRestantes ?? '—' }}</td>
        <td>{{ $r->Ubicacion }}</td>
        <td>{{ $r->ContenedorNumero }}</td>
      </tr>
    @empty
      <tr><td colspan="7">Sin datos para imprimir.</td></tr>
    @endforelse
  </tbody>
</table>

<script>window.print?.();</script>
