<div class="p-3">
  <div class="text-xs text-gray-500 mb-2">
    Histórico (últimos snapshots) — Art. {{ $codigo }}
  </div>
  <table class="min-w-full text-xs">
    <thead>
      <tr class="text-left text-gray-600">
        <th class="py-1 pr-2">Snapshot</th>
        <th class="py-1 pr-2">Vence</th>
        <th class="py-1 pr-2 text-right">Unid.</th>
        <th class="py-1 pr-2 text-right">Δ</th>
        <th class="py-1 pr-2 text-right">Días</th>
      </tr>
    </thead>
    <tbody>
      @forelse($rows as $r)
        @php
          $delta = $r->delta_unidades;
          $b = 'bg-gray-100 text-gray-700 ring-1 ring-gray-200'; $ico = '—';
          if (!is_null($delta)) {
            if ($delta < 0) { $b = 'bg-green-100 text-green-700 ring-1 ring-green-200'; $ico = '▼'; }
            elseif ($delta == 0) { $b = 'bg-red-100 text-red-700 ring-1 ring-red-200'; $ico = '■'; }
            else { $b = 'bg-gray-100 text-gray-700 ring-1 ring-gray-200'; $ico = '▲'; }
          }
        @endphp
        <tr class="border-t border-gray-100">
          <td class="py-1 pr-2">{{ \Carbon\Carbon::parse($r->fechaHoy)->format('d/m/Y') }}</td>
          <td class="py-1 pr-2">{{ \Carbon\Carbon::parse($r->fechaVencimiento)->format('d/m/Y') }}</td>
          <td class="py-1 pr-2 text-right">{{ number_format($r->Unidades ?? 0, 0, ',', '.') }}</td>
          <td class="py-1 pr-2 text-right">
            @php
                $hoverText = 'Artículo nuevo';
                if (!is_null($delta)) {
                    $prev = ($r->Unidades ?? 0) - $delta;
                    $hoverText = "Stock anterior: " . number_format($prev, 0, ',', '.');
                }
            @endphp
            <span title="{{ $hoverText }}" class="cursor-help inline-flex items-center gap-1 px-2 py-0.5 rounded-full {{ $b }}">
              <span class="font-semibold">{{ $ico }}</span>
              <span>{{ is_null($delta) ? '—' : number_format($delta,0,',','.') }}</span>
            </span>
          </td>
          <td class="py-1 pr-2 text-right">{{ $r->diasRestantes ?? '—' }}</td>
        </tr>
      @empty
        <tr><td colspan="5" class="py-2 text-gray-500">Sin snapshots.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
