<div class="space-y-3">
  <div class="text-xs text-gray-600">
    Artículo <span class="font-mono font-semibold">{{ $codigo }}</span>
  </div>

  <table class="min-w-full text-sm border rounded-lg overflow-hidden">
    <thead class="bg-gray-50">
      <tr class="text-left text-gray-600">
        <th class="p-2">Artículo</th>
        <th class="p-2">Vence</th>
        <th class="p-2">Snapshot (fechaHoy)</th>
        <th class="p-2 text-right">Unidades</th>
        <th class="p-2 text-right">Días restantes</th>
      </tr>
    </thead>
    <tbody>
      @forelse($rows as $r)
        <tr class="border-t">
          <td class="p-2 font-mono">{{ $r->ArticuloCodigo }}</td>
          <td class="p-2">{{ \Carbon\Carbon::parse($r->fechaVencimiento)->format('d/m/Y') }}</td>
          <td class="p-2">{{ \Carbon\Carbon::parse($r->fechaHoy)->format('d/m/Y') }}</td>
          <td class="p-2 text-right">{{ number_format($r->Unidades ?? 0, 0, ',', '.') }}</td>
          <td class="p-2 text-right">{{ $r->diasRestantes ?? '—' }}</td>
        </tr>
      @empty
        <tr><td colspan="5" class="p-3 text-center text-gray-500">Sin snapshots para este artículo.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
