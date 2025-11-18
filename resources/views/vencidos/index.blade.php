<x-app-layout>
  <x-slot name="header">
    <div class="flex items-center justify-between">
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Artículos Vencidos
      </h2>
      <span class="text-xs md:text-sm text-gray-500">
        Rango: {{ \Carbon\Carbon::parse($desde)->format('d/m/Y') }} — {{ \Carbon\Carbon::parse($hasta)->format('d/m/Y') }}
      </span>
    </div>
  </x-slot>

  <div class="mx-auto max-w-7xl p-4 md:p-8 space-y-4">

    {{-- KPIs --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div class="rounded-xl border border-[#012b67]/20 bg-white p-5 shadow-sm">
        <h3 class="text-sm font-semibold text-gray-600">Total artículos vencidos</h3>
        <div class="text-4xl font-extrabold mt-1 text-[#012b67]">
          {{ number_format($kpiTotalArt,0,',','.') }}
        </div>
        <p class="text-xs text-gray-500 mt-1">Según filtros aplicados</p>
      </div>

      <div class="rounded-xl border border-[#012b67]/20 bg-white p-5 shadow-sm">
        <h3 class="text-sm font-semibold text-gray-600">Unidades vencidas</h3>
        <div class="text-4xl font-extrabold mt-1 text-[#012b67]">
          {{ number_format($kpiUnidades,0,',','.') }}
        </div>
        <p class="text-xs text-gray-500 mt-1">unidades del primer corte vencido</p>
      </div>

      <div class="rounded-xl border border-[#012b67]/20 bg-white p-5 shadow-sm">
        <h3 class="text-sm font-semibold text-gray-600">Rango de vencimiento</h3>
        <div class="mt-2 text-sm text-gray-800">
          {{ \Carbon\Carbon::parse($desde)->format('d/m/Y') }}
          –
          {{ \Carbon\Carbon::parse($hasta)->format('d/m/Y') }}
        </div>
        <p class="text-xs text-gray-500 mt-1">
          Filtrado por fecha de vencimiento
        </p>
      </div>
    </div>

    {{-- Filtros --}}
    <form method="GET" action="{{ route('vencidos.index') }}"
          id="form-filtros"
          class="flex flex-wrap items-end gap-3 bg-white rounded-xl border p-4 shadow-sm">

      <div>
        <label class="block text-xs font-semibold text-gray-600 mb-1">Desde (vencimiento)</label>
        <input type="date" name="desde" value="{{ $desde }}"
               class="text-sm border rounded-lg px-3 py-2 focus:outline-none focus:ring focus:ring-blue-200">
      </div>

      <div>
        <label class="block text-xs font-semibold text-gray-600 mb-1">Hasta (vencimiento)</label>
        <input type="date" name="hasta" value="{{ $hasta }}"
               class="text-sm border rounded-lg px-3 py-2 focus:outline-none focus:ring focus:ring-blue-200">
      </div>

      <div class="min-w-[220px]">
        <label class="block text-xs font-semibold text-gray-600 mb-1">Buscar</label>
        <input type="text" name="q" value="{{ $q }}"
               placeholder="Código o descripción…"
               class="text-sm border rounded-lg px-3 py-2 w-full focus:outline-none focus:ring focus:ring-blue-200">
      </div>

      <div class="flex items-center gap-2 ml-auto">
        <button type="submit"
                class="inline-flex items-center gap-2 rounded-lg border px-4 py-2 text-sm font-medium hover:bg-gray-50 text-gray-700">
          Filtrar
        </button>
        <a href="{{ route('vencidos.index') }}"
           class="inline-flex items-center gap-2 rounded-lg border px-3 py-2 text-sm text-gray-600 hover:bg-gray-50">
          Limpiar
        </a>

        {{-- Botón imprimir --}}
        <button type="button"
                onclick="enviarVencidosImprimir()"
                class="inline-flex items-center gap-2 rounded-lg bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-black">
          🖨️ Imprimir resultados
        </button>
      </div>
    </form>

    {{-- Form oculto para imprimir --}}
    <form method="POST" action="{{ route('vencidos.print') }}" target="_blank" id="form-vencidos-print" class="hidden">
      @csrf
      <input type="hidden" name="desde" value="{{ $desde }}">
      <input type="hidden" name="hasta" value="{{ $hasta }}">
      <input type="hidden" name="q" value="{{ $q }}">
    </form>

    {{-- Tabla --}}
    <div class="overflow-x-auto bg-white rounded-xl border shadow-sm">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-50">
          <tr class="text-left text-gray-600">
            <th class="p-3">Artículo</th>
            <th class="p-3">Descripción</th>
            <th class="p-3">Vence</th>
            <th class="p-3">Primer día vencido</th>
            <th class="p-3 text-right">Días vencido</th>
            <th class="p-3 text-right">Unidades</th>
            <th class="p-3">Ubicación ej.</th>
            <th class="p-3">Contenedor ej.</th>
          </tr>
        </thead>
        <tbody class="[&>tr:nth-child(even)]:bg-gray-50">
          @forelse ($rows as $row)
            @php
              $vence      = \Carbon\Carbon::parse($row->fechaVencimiento);
              $primerDia  = \Carbon\Carbon::parse($row->fechaPrimerVencido);
              $diasVenc   = $primerDia->diffInDays(\Carbon\Carbon::today());
              $chipClass  = $diasVenc < 7
                              ? 'bg-yellow-100 text-yellow-800 ring-1 ring-yellow-200'
                              : 'bg-red-100 text-red-700 ring-1 ring-red-200';
            @endphp
            <tr class="border-t border-gray-100 hover:bg-blue-50/40 transition">
              <td class="p-3 font-mono text-gray-900">
                {{ $row->ArticuloCodigo }}
              </td>
              <td class="p-3 text-gray-800">
                {{ $row->ArticuloDescripcion }}
              </td>
              <td class="p-3">
                {{ $vence->format('d/m/Y') }}
              </td>
              <td class="p-3">
                {{ $primerDia->format('d/m/Y') }}
              </td>
              <td class="p-3 text-right">
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $chipClass }}">
                  {{ $diasVenc }}
                </span>
              </td>
              <td class="p-3 text-right tabular-nums">
                {{ number_format($row->unidadesPrimerVencido ?? 0, 0, ',', '.') }}
              </td>
              <td class="p-3 text-gray-700">
                {{ $row->UbicacionEjemplo }}
              </td>
              <td class="p-3 text-gray-700">
                {{ $row->ContenedorEjemplo }}
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="8" class="p-4 text-center text-gray-500">
                No se encontraron artículos vencidos para los filtros seleccionados.
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    {{-- Paginación --}}
    <div class="flex items-center justify-between">
      <div class="text-sm text-gray-600">
        {{ $rows->links() }}
      </div>
    </div>

  </div>

  <script>
    function enviarVencidosImprimir() {
      const formFiltro = document.getElementById('form-filtros');
      const formPrint  = document.getElementById('form-vencidos-print');

      const desde = formFiltro.querySelector('input[name="desde"]').value;
      const hasta = formFiltro.querySelector('input[name="hasta"]').value;
      const q     = formFiltro.querySelector('input[name="q"]').value;

      formPrint.querySelector('input[name="desde"]').value = desde;
      formPrint.querySelector('input[name="hasta"]').value = hasta;
      formPrint.querySelector('input[name="q"]').value     = q;

      formPrint.submit();
    }
  </script>
</x-app-layout>
