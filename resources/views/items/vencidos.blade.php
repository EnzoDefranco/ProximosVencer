{{-- resources/views/items/vencidos.blade.php --}}
<x-app-layout>
  <x-slot name="header">
    <div class="flex items-center justify-between">
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Vencidos (Snapshot)
      </h2>
      <div class="flex items-center gap-3 text-xs md:text-sm text-gray-500">
        <span>Corte: {{ isset($fechaHoy) ? \Carbon\Carbon::parse($fechaHoy)->format('d/m/Y') : '—' }}</span>
        <a href="{{ route('items.index') }}"
           class="inline-flex items-center gap-1 text-blue-600 hover:text-blue-700">
          ← Volver a listado
        </a>
      </div>
    </div>
  </x-slot>

  <div class="mx-auto max-w-7xl p-4 md:p-8 space-y-4">

    {{-- Resumen --}}
    <div class="rounded-xl border bg-white p-4 shadow-sm flex items-center justify-between">
      <div>
        <div class="text-sm text-gray-600">Artículos vencidos (ventana 45 días)</div>
        <div class="text-2xl font-semibold text-red-600">
          {{ number_format(($rows instanceof \Illuminate\Pagination\LengthAwarePaginator) ? $rows->total() : ($rows?->count() ?? 0), 0, ',', '.') }}
        </div>
      </div>
      <div class="text-right text-xs text-gray-500">
        Muestra ítems con <strong>Unidades &gt; 0</strong> y <strong>0 ≤ ({{ isset($fechaHoy) ? \Carbon\Carbon::parse($fechaHoy)->format('d/m/Y') : 'fh' }} − fechaVencimiento) ≤ 45</strong>.
      </div>
    </div>

    {{-- Tabla --}}
    <div class="overflow-x-auto bg-white rounded-xl border shadow-sm">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-50">
          <tr class="text-left text-gray-600">
            <th class="p-3">Código</th>
            <th class="p-3">Descripción</th>
            <th class="p-3">Vence</th>
            <th class="p-3 text-right">Unidades</th>
            <th class="p-3 text-right">Días</th>
            <th class="p-3">Ubicación</th>
            <th class="p-3">Contenedor</th>
          </tr>
        </thead>

        <tbody class="[&>tr:nth-child(even)]:bg-gray-50">
          @forelse($rows as $r)
            @php
              $vence = isset($r->fechaVencimiento) ? \Carbon\Carbon::parse($r->fechaVencimiento)->format('d/m/Y') : '—';
              $dias  = $r->diasRestantes ?? null;
              $chip  = is_null($dias) ? 'bg-gray-200 text-gray-700'
                    : ($dias <= 0 ? 'bg-red-100 text-red-700 ring-1 ring-red-200'
                                   : 'bg-yellow-100 text-yellow-800 ring-1 ring-yellow-200');
            @endphp
            <tr class="border-t border-gray-100">
              <td class="p-3 font-mono text-gray-900">{{ $r->ArticuloCodigo }}</td>
              <td class="p-3 text-gray-800">{{ $r->ArticuloDescripcion ?? '—' }}</td>
              <td class="p-3">{{ $vence }}</td>
              <td class="p-3 text-right tabular-nums">
                {{ number_format((int)($r->Unidades ?? 0), 0, ',', '.') }}
              </td>
              <td class="p-3 text-right">
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $chip }}">
                  {{ $dias ?? '—' }}
                </span>
              </td>
              <td class="p-3">{{ $r->Ubicacion ?? '—' }}</td>
              <td class="p-3">{{ $r->ContenedorNumero ?? '—' }}</td>
            </tr>
          @empty
            <tr>
              <td colspan="7" class="p-6 text-center text-gray-500">
                No hay artículos vencidos para este corte dentro de los últimos 45 días.
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    {{-- Paginación --}}
    @if($rows instanceof \Illuminate\Pagination\LengthAwarePaginator)
      <div class="flex items-center justify-between">
        <div class="text-sm text-gray-600">{{ $rows->links() }}</div>
      </div>
    @endif

    {{-- Pie: acción rápida --}}
    <div class="flex items-center justify-end gap-2">
      <button onclick="window.print()"
              class="inline-flex items-center gap-2 rounded-lg bg-gray-700 px-4 py-2 text-white text-sm font-medium hover:bg-gray-800 focus:outline-none focus:ring focus:ring-gray-200">
        🖨️ Imprimir
      </button>
    </div>

  </div>
</x-app-layout>
