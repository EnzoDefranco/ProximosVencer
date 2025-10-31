<x-app-layout>
  <x-slot name="header">
    <div class="flex items-center justify-between">
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">Próximos Vencimientos</h2>
      <span class="text-xs md:text-sm text-gray-500">
        Última sincronización: {{ $ultimaSync ? \Carbon\Carbon::parse($ultimaSync)->format('d/m/Y H:i') : '—' }}
      </span>
    </div>
  </x-slot>

  <div class="mx-auto max-w-7xl p-4 md:p-8 space-y-4">

    {{-- KPIs --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div class="rounded-xl border bg-white p-4 shadow-sm">
        <div class="flex items-center justify-between mb-2">
          <h3 class="text-sm font-semibold text-gray-600">Total artículos</h3>
          <span class="text-gray-400">◎</span>
        </div>
        <div class="text-2xl font-semibold">{{ number_format($stats['total'],0,',','.') }}</div>
        <p class="mt-1 text-xs text-gray-500">Snapshot: {{ isset($fechaHoy) ? \Carbon\Carbon::parse($fechaHoy)->format('d/m/Y') : '—' }}</p>
      </div>

      <div class="rounded-xl border bg-white p-4 shadow-sm">
        <div class="flex items-center justify-between mb-2">
          <h3 class="text-sm font-semibold text-gray-600">Validados</h3>
          <span class="text-gray-400">🗓️</span>
        </div>
        <div class="flex items-baseline gap-2">
          <div id="kpi-val" class="text-2xl font-semibold">{{ number_format($stats['validados'],0,',','.') }}</div>
          <div class="text-sm text-gray-500"><span id="kpi-val-pct">{{ $stats['porc'] }}</span>% del total</div>
        </div>
      </div>

      <div class="rounded-xl border bg-white p-4 shadow-sm">
        <div class="flex items-center justify-between mb-2">
          <h3 class="text-sm font-semibold text-gray-600">Urgentes</h3>
          <span class="text-gray-400">⏳</span>
        </div>
        <div class="text-2xl font-semibold text-red-600">
          {{ number_format($stats['urgentes'],0,',','.') }}
        </div>
        <p class="mt-1 text-xs text-gray-500">Vencen en 7 días o menos</p>
      </div>
    </div>

    {{-- Buscador simple --}}
    <form method="GET" action="{{ route('items.index') }}"
          class="flex flex-wrap items-center gap-3 bg-white rounded-xl border p-3 shadow-sm">
      <div class="flex items-center gap-2">
        <input type="text" name="q" value="{{ $q ?? '' }}" placeholder="Buscar código o descripción…"
               class="text-sm border rounded-lg px-3 py-2 focus:outline-none focus:ring focus:ring-blue-200 min-w-[240px]">
        @if(($q ?? '') !== '')
          <a href="{{ route('items.index') }}"
             class="text-sm text-gray-600 hover:text-gray-800 underline">Limpiar</a>
        @endif
        <button class="hidden md:inline-flex text-sm rounded-lg border px-3 py-2 hover:bg-gray-50" type="submit">Buscar</button>
      </div>

      <div class="ml-auto flex items-center gap-2 text-xs text-gray-500">
        <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-red-500"></span> ≤7 días</span>
        <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-yellow-400"></span> 8–30 días</span>
        <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-green-500"></span> >30 días</span>
      </div>
    </form>

    @if (session('ok'))
      <div class="rounded-xl border border-green-300 bg-green-50 text-green-800 px-4 py-3 shadow-sm">
        {{ session('ok') }}
      </div>
    @endif

    <form method="POST" action="{{ route('items.confirmar') }}" class="space-y-3">
      @csrf
      <input type="hidden" name="fechaHoy" value="{{ $fechaHoy }}">

      {{-- Barra de acciones --}}
      <div class="top-24 md:top-24 z-10 bg-white/90 backdrop-blur border rounded-xl px-3 py-2 shadow-sm flex items-center justify-between">
        <div class="text-sm text-gray-600">
          Mostrando {{ $items->firstItem() }}–{{ $items->lastItem() }} de {{ $items->total() }}
          @if(($q ?? '') !== '') · <span class="italic">Filtro: “{{ $q }}”</span> @endif
          · <span class="text-gray-600">Snapshot actual</span>
        </div>
        @if ($puedeEditar)
          <button type="submit"
                  class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-white text-sm font-medium hover:bg-blue-700 active:bg-blue-800 focus:outline-none focus:ring focus:ring-blue-200">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M2.25 12a9.75 9.75 0 1119.5 0 9.75 9.75 0 01-19.5 0zm14.03-2.53a.75.75 0 00-1.06-1.06l-5.72 5.72-2.22-2.22a.75.75 0 10-1.06 1.06l2.75 2.75c.3.3.79.3 1.06 0l6.25-6.25z" clip-rule="evenodd"/></svg>
            Confirmar cambios
          </button>
        @endif
      </div>

      {{-- Tabla --}}
      <div class="overflow-x-auto bg-white rounded-xl border shadow-sm">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 top-40 md:top-40 z-10">
            <tr class="text-left text-gray-600">
              <th class="p-3 w-12">OK</th>
              <th class="p-3">Artículo</th>
              <th class="p-3">Creado</th>
              <th class="p-3">Descripción</th>
              <th class="p-3">Vence</th>
              <th class="p-3 text-right">Unidades</th>
              <th class="p-3 text-right">Δ Unid</th> {{-- hover del historial acá --}}
              <th class="p-3 text-right">Días</th>
            </tr>
          </thead>
          <tbody class="[&>tr:nth-child(even)]:bg-gray-50">
            @foreach ($items as $row)
              @php
                $d = $row->diasRestantes;
                $chip = is_null($d) ? 'bg-gray-200 text-gray-700'
                      : ($d <= 7 ? 'bg-red-100 text-red-700 ring-1 ring-red-200'
                      : ($d <= 30 ? 'bg-yellow-100 text-yellow-800 ring-1 ring-yellow-200'
                                  : 'bg-green-100 text-green-700 ring-1 ring-green-200'));

                // Δ del current (si lo trae el SELECT)
                $delta = $row->delta_unidades ?? null;

                // Colores Δ:
                // null → AMARILLO (NEW),  <0 → VERDE (se vendió),  =0 → ROJO (sin cambio),  >0 → GRIS (ingresó)
                $deltaBadge = 'bg-yellow-100 text-yellow-800 ring-1 ring-yellow-200'; // default (null = NEW)
                $deltaIcon  = '●';
                $deltaText  = 'NEW';
                if (!is_null($delta)) {
                    if ($delta < 0) { $deltaBadge = 'bg-green-100 text-green-700 ring-1 ring-green-200'; $deltaIcon = '▼'; $deltaText = number_format($delta,0,',','.'); }
                    elseif ($delta == 0) { $deltaBadge = 'bg-red-100 text-red-700 ring-1 ring-red-200'; $deltaIcon = '■'; $deltaText = '0'; }
                    else { $deltaBadge = 'bg-gray-100 text-gray-700 ring-1 ring-gray-200'; $deltaIcon = '▲'; $deltaText = number_format($delta,0,',','.'); }
                }

                // Title dinámico para el badge Δ
                if (is_null($delta)) {
                  $deltaTitle = 'Artículo nuevo en el snapshot (Δ no disponible)';
                } elseif ($delta < 0) {
                  $deltaTitle = 'Bajó ' . number_format(abs($delta), 0, ',', '.') . ' unidades vs snapshot anterior';
                } elseif ($delta == 0) {
                  $deltaTitle = 'Sin cambios vs snapshot anterior';
                } else {
                  $deltaTitle = 'Subió ' . number_format($delta, 0, ',', '.') . ' unidades vs snapshot anterior';
                }
              @endphp

              <tr class="border-t border-gray-100 hover:bg-blue-50/40 transition">
                <td class="p-3 text-center">
                  <input type="hidden" name="visible[]" value="{{ $row->id }}">
                  @if ($puedeEditar)
                    <input type="checkbox"
                           name="checked[]"
                           value="{{ $row->id }}"
                           @checked($row->checked)
                           class="h-4 w-4 accent-blue-600 kpi-watch">
                  @else
                    <input type="checkbox" disabled @checked($row->checked) class="h-4 w-4 opacity-60">
                  @endif
                </td>

                <td class="p-3 font-mono text-gray-900">{{ $row->ArticuloCodigo }}</td>
                <td class="p-3 text-gray-800">{{ \Carbon\Carbon::parse($row->created_at)->format('d/m/Y') }}</td>
                <td class="p-3 text-gray-800">{{ $row->ArticuloDescripcion }}</td>
                <td class="p-3">{{ \Carbon\Carbon::parse($row->fechaVencimiento)->format('d/m/Y') }}</td>
                <td class="p-3 text-right tabular-nums">{{ number_format($row->Unidades ?? 0, 0, ',', '.') }}</td>

                {{-- Δ del current con HOVER del histórico (sin columna Hist.) --}}
                <td class="p-3 text-right">
                  <span x-data="hoverHist('{{ route('items.historial', ['codigo' => $row->ArticuloCodigo]) }}?compact=1')"
                        x-on:mouseenter="open($event)" x-on:mouseleave="close($event)"
                        class="relative inline-flex">
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium {{ $deltaBadge }}"
                          title="{{ $deltaTitle }}" aria-label="{{ $deltaTitle }}">
                      <span class="font-semibold">{{ $deltaIcon }}</span>
                      <span>{{ $deltaText }}</span>
                    </span>
                    {{-- Tooltip / Hovercard pegado al badge --}}
                    <div x-show="show"
                         x-transition
                         class="absolute right-0 mt-2 w-[520px] max-w-[90vw] rounded-xl border bg-white shadow-2xl z-50"
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

      {{-- Paginación --}}
      <div class="flex items-center justify-between">
        <div class="text-sm text-gray-600">{{ $items->links() }}</div>
      </div>
    </form>

  </div>

  {{-- KPI en vivo (opcional) --}}
  @if ($puedeEditar)
  <script>
    (function () {
      const total     = {{ (int)$stats['total'] }};
      const startVal  = {{ (int)$stats['validados'] }};
      let delta       = 0;
      const elVal     = document.getElementById('kpi-val');
      const elPct     = document.getElementById('kpi-val-pct');
      const elChecks  = document.querySelectorAll('input.kpi-watch[type="checkbox"]');

      let pageCheckedInitial = 0;
      elChecks.forEach(ch => { if (ch.checked) pageCheckedInitial++; });

      function recalc() {
        let currentPageChecked = 0;
        elChecks.forEach(ch => { if (ch.checked) currentPageChecked++; });
        delta = currentPageChecked - pageCheckedInitial;

        const shown = Math.max(0, startVal + delta);
        elVal.textContent = new Intl.NumberFormat('es-AR').format(shown);
        elPct.textContent = total ? Math.round((shown * 100) / total) : 0;
      }

      elChecks.forEach(ch => ch.addEventListener('change', recalc));
    })();
  </script>
  @endif

  {{-- Hovercard de historial (ligero, sin abrir nada) --}}
  <script>
    function hoverHist(url) {
      return {
        show: false,
        body: '<div class="p-4 text-sm text-gray-500">Cargando…</div>',
        _timer: null,
        async open(e) {
          clearTimeout(this._timer);
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

  {{-- Alpine --}}
  <script> window.deferAlpineInit = true; </script>
  <script src="https://unpkg.com/alpinejs" defer></script>
</x-app-layout>
