<x-app-layout>
  <x-slot name="header">
    <div class="flex items-center justify-between">
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">Movimientos de vencimiento (Supervisor)</h2>
      <div class="text-xs md:text-sm text-gray-500">
        Corte: {{ $fechaHoy ? \Carbon\Carbon::parse($fechaHoy)->format('d/m/Y') : '—' }}
      </div>
    </div>
  </x-slot>

  <div class="mx-auto max-w-7xl p-4 md:p-8 space-y-4">
    <form method="GET" action="{{ route('items.corregidos') }}" class="bg-white border rounded-xl p-3 shadow-sm flex flex-wrap gap-2 items-center">
      <select name="tipo" class="border rounded px-2 py-1 text-sm">
        <option value="">Todos</option>
        <option value="CORREGIDO" @selected(($filtros['tipo'] ?? '')==='CORREGIDO')>Corregidos</option>
        <option value="DESAPARECIDO" @selected(($filtros['tipo'] ?? '')==='DESAPARECIDO')>Desaparecidos</option>
      </select>
      <input type="text" name="q" value="{{ $filtros['q'] ?? '' }}" placeholder="Código..."
             class="border rounded px-3 py-1 text-sm">
      <button class="border rounded px-3 py-1 text-sm hover:bg-gray-50">Filtrar</button>
      @if(($filtros['tipo'] ?? '')!=='' || ($filtros['q'] ?? '')!=='')
        <a href="{{ route('items.corregidos') }}" class="text-sm underline">Limpiar</a>
      @endif
    </form>

    @if ($rows->isEmpty())
      <div class="rounded-xl border bg-white p-6 text-gray-600">Sin movimientos registrados en el corte.</div>
    @else
      <div class="overflow-x-auto bg-white rounded-xl border shadow-sm">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50">
            <tr class="text-left text-gray-600">
              <th class="p-3">Tipo</th>
              <th class="p-3">Código</th>
              <th class="p-3">Descripción</th>
              <th class="p-3">Vto anterior</th>
              <th class="p-3">Vto actual</th>
              <th class="p-3 text-right">Unid. antes</th>
              <th class="p-3 text-right">Unid. hoy</th>
              <th class="p-3">Visto por última vez</th>
              <th class="p-3">Corte actual</th>
            </tr>
          </thead>
          <tbody class="[&>tr:nth-child(even)]:bg-gray-50">
            @foreach ($rows as $r)
              @php
                $chip = $r->tipo === 'CORREGIDO'
                  ? 'bg-blue-100 text-blue-700 ring-1 ring-blue-200'
                  : 'bg-gray-200 text-gray-700 ring-1 ring-gray-300';
              @endphp
              <tr class="border-t border-gray-100">
                <td class="p-3">
                  <span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $chip }}">{{ $r->tipo }}</span>
                </td>
                <td class="p-3 font-mono">{{ $r->articulo_codigo }}</td>
                <td class="p-3">{{ $r->articulo_descripcion }}</td>
                <td class="p-3">{{ $r->vto_anterior ? \Carbon\Carbon::parse($r->vto_anterior)->format('d/m/Y') : '—' }}</td>
                <td class="p-3">{{ $r->vto_actual ? \Carbon\Carbon::parse($r->vto_actual)->format('d/m/Y') : '—' }}</td>
                <td class="p-3 text-right">{{ number_format($r->unidades_prev ?? 0, 0, ',', '.') }}</td>
                <td class="p-3 text-right">{{ number_format($r->unidades_actual ?? 0, 0, ',', '.') }}</td>
                <td class="p-3">{{ $r->fh_anterior ? \Carbon\Carbon::parse($r->fh_anterior)->format('d/m/Y') : '—' }}</td>
                <td class="p-3">{{ $r->fh_actual ? \Carbon\Carbon::parse($r->fh_actual)->format('d/m/Y') : '—' }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </div>
</x-app-layout>
