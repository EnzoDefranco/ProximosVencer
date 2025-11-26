<x-app-layout>
  <x-slot name="header">
    <div class="flex items-center justify-between">
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">Próximos Vencimientos</h2>
      <span class="text-xs md:text-sm text-gray-500">
        Última sincronización:
        {{ $ultimaSync ? \Carbon\Carbon::parse($ultimaSync)->format('d/m/Y H:i') : '—' }}
      </span>
    </div>
  </x-slot>

  <div class="mx-auto max-w-7xl p-4 md:p-8 space-y-4">

    {{-- ========================================================= --}}
    {{-- ======================= KPIs =========================== --}}
    {{-- ========================================================= --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">

      {{-- TOTAL ARTÍCULOS --}}
      <div class="rounded-xl border border-[#012b67]/20 bg-white p-5 shadow-sm hover:shadow transition">
        <h3 class="text-sm font-semibold text-gray-600">Total artículos</h3>
        <div class="text-4xl font-extrabold mt-1 text-[#012b67]">
          {{ number_format($stats['total'], 0, ',', '.') }}
        </div>
        <p class="text-xs text-gray-500 mt-1">Corte: {{ \Carbon\Carbon::parse($fechaHoy)->format('d/m') }}</p>
      </div>

      {{-- VALIDADOS --}}
      <div class="rounded-xl border border-[#012b67]/20 bg-white p-5 shadow-sm hover:shadow transition">
        <h3 class="text-sm font-semibold text-gray-600">Validados</h3>
        <div class="flex items-baseline gap-2 mt-1">
          <div id="kpi-val" class="text-4xl font-extrabold text-[#012b67]">
            {{ number_format($stats['validados'], 0, ',', '.') }}
          </div>
          <span class="text-sm text-gray-600">
            <span id="kpi-val-pct">{{ $stats['porc'] }}</span>% del total
          </span>
        </div>
      </div>

      {{-- VENCIDOS 7 DÍAS --}}
      <a href="{{ route('vencidos.index', ['desde' => now()->subDays(7)->toDateString(), 'hasta' => now()->toDateString()]) }}"
        class="rounded-xl border border-red-300 bg-white p-5 shadow-sm hover:shadow hover:bg-red-50 transition block">
        <h3 class="text-sm font-semibold text-gray-600">Vencidos últimos 7 días</h3>
        <div class="text-4xl font-extrabold mt-1 text-red-600">
          {{ number_format($kpiVencidos, 0, ',', '.') }}
        </div>
        <p class="mt-1 text-xs text-gray-500">Click para ver</p>
      </a>


      {{-- PRÓXIMOS 30 DÍAS --}}
      <div class="rounded-xl border border-[#012b67]/20 bg-white p-5 shadow-sm hover:shadow transition">
        <h3 class="text-sm font-semibold text-gray-600">Vencen en próximos 30 días</h3>
        <div class="text-4xl font-extrabold mt-1 text-[#012b67]">
          {{ number_format($kpiMovimientos, 0, ',', '.') }}
        </div>
      </div>

    </div>

    {{-- ========================================================= --}}
    {{-- ==================== BUSCADOR ========================= --}}
    {{-- ========================================================= --}}
    <form method="GET" action="{{ route('items.index') }}"
      class="flex flex-wrap items-center gap-3 bg-white rounded-xl border p-3 shadow-sm">

      <div class="flex items-center gap-2">
        <input type="text" name="q" value="{{ $q ?? '' }}" placeholder="Buscar código o descripción…"
          class="text-sm border rounded-lg px-3 py-2 focus:outline-none focus:ring focus:ring-blue-200 min-w-[240px]">

        @if(($q ?? '') !== '')
          <a href="{{ route('items.index') }}" class="text-sm text-gray-600 hover:text-gray-800 underline">Limpiar</a>
        @endif

        <button class="hidden md:inline-flex text-sm rounded-lg border px-3 py-2 hover:bg-gray-50">
          Buscar
        </button>
      </div>

      <div class="ml-auto flex items-center gap-2 text-xs text-gray-500">
        <span class="inline-flex items-center gap-1">
          <span class="h-2 w-2 rounded-full bg-red-500"></span> ≤7 días
        </span>
        <span class="inline-flex items-center gap-1">
          <span class="h-2 w-2 rounded-full bg-yellow-400"></span> 8–30 días
        </span>
        <span class="inline-flex items-center gap-1">
          <span class="h-2 w-2 rounded-full bg-green-500"></span> >30 días
        </span>
      </div>
    </form>

    @if (session('ok'))
      <div class="rounded-xl border border-green-300 bg-green-50 text-green-800 px-4 py-3 shadow-sm">
        {{ session('ok') }}
      </div>
    @endif

    {{-- ========================================================= --}}
    {{-- FORM imprimir oculto --}}
    {{-- ========================================================= --}}
    <form method="POST" action="{{ route('items.imprimir') }}" target="_blank" id="form-imprimir" class="hidden">
      @csrf
    </form>

    {{-- ========================================================= --}}
    {{-- FORM confirmar --}}
    {{-- ========================================================= --}}
    <form method="POST" action="{{ route('items.confirmar') }}" class="space-y-3" id="form-confirmar">
      @csrf
      <input type="hidden" name="fechaHoy" value="{{ $fechaHoy }}">

      {{-- barra acciones --}}
      <div
        class="z-10 bg-white/90 backdrop-blur border rounded-xl px-3 py-2 shadow-sm flex flex-wrap gap-2 items-center justify-between">
        <div class="text-sm text-gray-600">
          Mostrando {{ $items->firstItem() }}–{{ $items->lastItem() }} de {{ $items->total() }}
          @if(($q ?? '') !== '') · <span class="italic">Filtro: “{{ $q }}”</span> @endif
          · Snapshot actual
        </div>

        <div class="flex items-center gap-2">
          @if ($puedeEditar)
            <button type="submit"
              class="inline-flex items-center gap-2 rounded-lg bg-[#012b67] px-4 py-2 text-white text-sm font-medium hover:bg-blue-700">
              Guardar cambios
            </button>

            <button type="button" onclick="enviarAImprimir()"
              class="inline-flex items-center gap-2 rounded-lg bg-gray-700 px-4 py-2 text-white text-sm font-medium hover:bg-gray-800">
              🖨️ Imprimir pendientes
            </button>
          @endif
        </div>
      </div>

      {{-- ========================================================= --}}
      {{-- ====================== TABLA ============================ --}}
      {{-- ========================================================= --}}

      <div class="overflow-x-auto bg-white rounded-xl border shadow-sm">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50">
            <tr class="text-left text-gray-600">
              <th class="p-3 w-12">OK</th>
              <th class="p-3">Artículo</th>
              <th class="p-3">Creado</th>
              <th class="p-3">Descripción</th>
              <th class="p-3">Vence</th>
              <th class="p-3 text-right">Unidades</th>
              <th class="p-3 text-right">Δ Unid</th>
              <th class="p-3 text-right">Días</th>
            </tr>
          </thead>

          <tbody class="[&>tr:nth-child(even)]:bg-gray-50">

            @foreach ($items as $row)
              @php
                $d = $row->diasRestantes;

                $chip = is_null($d)
                  ? 'bg-gray-200 text-gray-700'
                  : ($d <= 7 ? 'bg-red-100 text-red-700 ring-1 ring-red-200'
                    : ($d <= 30 ? 'bg-yellow-100 text-yellow-800 ring-1 ring-yellow-200'
                      : 'bg-green-100 text-green-700 ring-1 ring-green-200'));

                $delta = $row->delta_unidades;
                $deltaBadge = 'bg-yellow-100 text-yellow-800 ring-1 ring-yellow-200';
                $deltaIcon = '●';
                $deltaText = 'NEW';

                if (!is_null($delta)) {
                  if ($delta < 0) {
                    $deltaBadge = 'bg-green-100 text-green-700 ring-1 ring-green-200';
                    $deltaIcon = '▼';
                    $deltaText = $delta;
                  } elseif ($delta == 0) {
                    $deltaBadge = 'bg-red-100 text-red-700 ring-1 ring-red-200';
                    $deltaIcon = '■';
                    $deltaText = '0';
                  } else {
                    $deltaBadge = 'bg-gray-100 text-gray-700 ring-1 ring-gray-200';
                    $deltaIcon = '▲';
                    $deltaText = $delta;
                  }
                }

                $deltaTitle = is_null($delta) ? 'Artículo nuevo en snapshot' :
                  ($delta < 0 ? 'Bajó ' . number_format(abs($delta), 0, ',', '.') :
                    ($delta == 0 ? 'Sin cambios' : 'Subió ' . number_format($delta, 0, ',', '.')));

                if (!is_null($delta)) {
                  $prev = ($row->Unidades ?? 0) - $delta;
                  $deltaTitle .= " | Stock anterior: " . number_format($prev, 0, ',', '.');
                }
              @endphp

              <tr class="border-t border-gray-100 hover:bg-blue-50/40 transition">

                <td class="p-3 text-center">
                  <input type="hidden" name="visible[]" value="{{ $row->id }}">
                  <input type="checkbox" name="checked[]" value="{{ $row->id }}" class="h-4 w-4 accent-blue-600 kpi-watch"
                    @checked($row->checked) @disabled(!$puedeEditar)>
                </td>

                <td class="p-3 font-mono text-gray-900">{{ $row->ArticuloCodigo }}</td>
                <td class="p-3 text-gray-800">{{ \Carbon\Carbon::parse($row->created_at)->format('d/m/Y') }}</td>
                <td class="p-3 text-gray-800">{{ $row->ArticuloDescripcion }}</td>
                <td class="p-3">{{ \Carbon\Carbon::parse($row->fechaVencimiento)->format('d/m/Y') }}</td>

                <td class="p-3 text-right tabular-nums">{{ number_format($row->Unidades ?? 0, 0, ',', '.') }}</td>

                {{-- Δ con hover historial (paso vto en query) --}}
                <td class="p-3 text-right">
                  <span
                    x-data="hoverHist('{{ route('items.historial', ['codigo' => $row->ArticuloCodigo]) }}?vto={{ \Carbon\Carbon::parse($row->fechaVencimiento)->toDateString() }}&compact=1')"
                    x-on:mouseenter="open($event)" x-on:mouseleave="close($event)" class="relative inline-flex">
                    <span
                      class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium {{ $deltaBadge }}"
                      title="{{ $deltaTitle }}" aria-label="{{ $deltaTitle }}">
                      <span class="font-semibold">{{ $deltaIcon }}</span>
                      <span>{{ $deltaText }}</span>
                    </span>
                    <div x-show="show" x-transition :class="placement === 'top' ? 'bottom-full mb-2' : 'mt-2'"
                      class="absolute right-0 w-[520px] max-w-[90vw] rounded-xl border bg-white shadow-2xl z-50"
                      x-html="body">
                    </div>
                  </span>
                </td>

                <td class="p-3 text-right">
                  <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $chip }}">
                    {{ $d ?? '—' }}
                  </span>
                </td>
              </tr>

            @endforeach

          </tbody>

        </table>
      </div>

      {{-- PAGINACIÓN --}}
      <div class="flex items-center justify-between">
        <div class="text-sm text-gray-600">
          {{ $items->links() }}
        </div>
      </div>

    </form>
  </div>

  {{-- ========================================================= --}}
  {{-- ============ KPI Live (Validados en Vivo) =============== --}}
  {{-- ========================================================= --}}
  <script>
    (function () {
      const total = {{ (int) $stats['total'] }};
      const startVal = {{ (int) $stats['validados'] }};
      const elVal = document.getElementById('kpi-val');
      const elPct = document.getElementById('kpi-val-pct');
      const elChecks = document.querySelectorAll('input.kpi-watch');

      let initialChecked = 0;
      elChecks.forEach(ch => { if (ch.checked) initialChecked++; });

      function recalc() {
        let now = 0;
        elChecks.forEach(ch => { if (ch.checked) now++; });

        const newVal = startVal + (now - initialChecked);
        elVal.textContent = newVal.toLocaleString('es-AR');
        elPct.textContent = total ? Math.round((newVal * 100) / total) : 0;
      }

      elChecks.forEach(ch => ch.addEventListener('change', recalc));
    })();
  </script>

  <script>
    function hoverHist(url) {
      return {
        show: false,
        placement: 'bottom',
        body: '<div class="p-4 text-sm text-gray-500">Cargando…</div>',
        _timer: null,
        async open(e) {
          clearTimeout(this._timer);

          // Calcular posición
          const rect = this.$el.getBoundingClientRect();
          const spaceBelow = window.innerHeight - rect.bottom;
          // Si hay menos de 300px abajo, mostrar arriba
          if (spaceBelow < 300) {
            this.placement = 'top';
          } else {
            this.placement = 'bottom';
          }

          this.show = true;
          try {
            const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            this.body = await res.text();
          } catch (err) {
            this.body = '<div class="p-4 text-sm text-red-600">Error cargando histórico</div>';
          }
        },
        close() { this._timer = setTimeout(() => { this.show = false; }, 120); }
      }
    }
  </script>

  {{-- ========================================================= --}}
  {{-- ================= BOTÓN IMPRIMIR ======================== --}}
  {{-- ========================================================= --}}
  <script>
    function enviarAImprimir() {
      const formImprimir = document.getElementById('form-imprimir');
      formImprimir.innerHTML = `@csrf`;

      // agregar checked[]
      document.querySelectorAll('input[name="checked[]"]:checked')
        .forEach(ch => {
          const i = document.createElement('input');
          i.type = 'hidden';
          i.name = 'checked[]';
          i.value = ch.value;
          formImprimir.appendChild(i);
        });

      // agregar fechaHoy
      const fh = document.querySelector('#form-confirmar input[name="fechaHoy"]').value;
      const fhInput = document.createElement('input');
      fhInput.type = 'hidden';
      fhInput.name = 'fechaHoy';
      fhInput.value = fh;
      formImprimir.appendChild(fhInput);

      if (!formImprimir.querySelector('input[name="checked[]"]')) {
        alert('No hay artículos tildados.');
        return;
      }

      formImprimir.submit();
    }
  </script>

  <script> window.deferAlpineInit = true; </script>
  <script src="https://unpkg.com/alpinejs" defer></script>

</x-app-layout>