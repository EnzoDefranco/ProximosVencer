{{-- resources/views/fichajes/index.blade.php --}}
<x-app-layout>
  <div class="px-6 py-4"
       x-data="fichajesPage('{{ route('fichajes.detalle', ['empleado'=>'__EMP__']) }}', '{{ $fecha }}')">

    {{-- Título + filtros --}}
    <div class="flex items-center justify-between mb-4 gap-2 flex-wrap">
      <h1 class="text-2xl font-semibold">Seguimiento de Vendedores</h1>
      <form method="get" class="flex flex-wrap gap-2 items-center">
        <input type="date" name="fecha" value="{{ $fecha }}" class="border rounded px-2 py-1">
        <select name="zona" class="border rounded px-2 py-1">
          <option value="">Todas las zonas</option>
          @foreach($zonas as $id => $nombre)
            <option value="{{ $id }}" @selected($zona===$id)>{{ $nombre }}</option>
          @endforeach
        </select>
        <input type="text" name="q" value="{{ $q }}" placeholder="Buscar vendedor/cliente" class="border rounded px-2 py-1 w-64">
        <button class="bg-blue-600 text-white rounded px-3 py-1">Filtrar</button>
      </form>
    </div>

    {{-- KPIs --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
      <div class="bg-white rounded-xl shadow p-4">
        <div class="text-sm text-gray-500">Total Vendedores</div>
        <div class="text-2xl font-bold">{{ $kpis->total_vendedores ?? 0 }}</div>
      </div>
      <div class="bg-white rounded-xl shadow p-4">
        <div class="text-sm text-gray-500">A Tiempo</div>
        <div class="text-2xl font-bold">{{ $kpis->a_tiempo ?? 0 }}</div>
      </div>
      <div class="bg-white rounded-xl shadow p-4">
        <div class="text-sm text-gray-500">Total Checkins</div>
        <div class="text-2xl font-bold">{{ $kpis->total_checkins ?? 0 }}</div>
      </div>
      <div class="bg-white rounded-xl shadow p-4">
        <div class="text-sm text-gray-500">Puntualidad</div>
        <div class="text-2xl font-bold">{{ $kpis->puntualidad_pct ?? 0 }}%</div>
      </div>
    </div>

    {{-- Tabla --}}
    <div class="bg-white rounded-xl shadow overflow-hidden">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-2 text-left">Vendedor</th>
            <th class="px-4 py-2">Zona</th>
            <th class="px-4 py-2">Primer Checkin</th>
            <th class="px-4 py-2">Estado</th>
            <th class="px-4 py-2">Checkins</th>
            <th class="px-4 py-2">Detalles</th>
          </tr>
        </thead>
        <tbody class="divide-y">
          @foreach($items as $row)
            <tr class="{{ $row->llegoATiempo ? 'bg-green-50' : '' }}">
              <td class="px-4 py-2">
                <div class="font-medium">{{ $row->nombreVendedor }}</div>
                <div class="text-gray-500 text-xs">{{ $row->codigoEmpleado }}</div>
              </td>
              <td class="px-4 py-2 text-center">
                <span class="px-2 py-1 bg-gray-100 rounded">{{ $row->zonaDescripcion }}</span>
              </td>
              <td class="px-4 py-2 text-center">
                {{ $row->primerCheckinValidoHora ?? '—' }}
              </td>
              <td class="px-4 py-2 text-center">
                @if($row->llegoATiempo)
                  <span class="text-green-700 bg-green-100 px-2 py-1 rounded">A tiempo</span>
                @else
                  <span class="text-amber-700 bg-amber-100 px-2 py-1 rounded">Tarde</span>
                @endif
              </td>
              <td class="px-4 py-2 text-center">
                {{ $row->totalCheckinsValidos }} / {{ $row->totalCheckins }}
              </td>
              <td class="px-4 py-2 text-center">
                <button type="button"
                        class="text-blue-600 hover:underline"
                        @click="toggle('{{ $row->codigoEmpleado }}')"
                        :aria-expanded="abierto==='{{ $row->codigoEmpleado }}'">
                  <span x-show="abierto!=='{{ $row->codigoEmpleado }}'">ver</span>
                  <span x-show="abierto==='{{ $row->codigoEmpleado }}'">ocultar</span>
                </button>
              </td>
            </tr>

            {{-- Detalle --}}
            <tr x-show="abierto==='{{ $row->codigoEmpleado }}'" x-transition>
              <td colspan="6" class="bg-gray-50 px-6 py-3">
                {{-- Lista de movimientos --}}
                <div class="space-y-2 text-sm text-gray-700"
                     x-show="(detalles['{{ $row->codigoEmpleado }}'] || []).length > 0">
                  <template x-for="(r,i) in (detalles['{{ $row->codigoEmpleado }}'] || [])"
                            :key="`${(r.timestampCheckin||'')}-${(r.codigoCliente||'')}-${i}`">
                    <div class="flex items-center justify-between rounded px-3 py-2"
                         :class="Number(r.valido)===1 ? 'bg-green-50' : 'bg-red-50'">
                      <div class="flex items-center gap-2">
                        <span x-text="Number(r.valido)===1 ? '✅' : '❌'"></span>
                        <div>
                          <span class="font-medium">Cliente:</span>
                          <span x-text="r.codigoCliente"></span>
                          <span class="text-gray-500" x-text="r.clienteCalle ?? ''"></span>
                          <div class="text-xs text-red-700"
                               x-show="Number(r.valido)!==1 && r.motivo"
                               x-text="`Motivo: ${r.motivo}`"></div>
                        </div>
                      </div>
                      <div class="text-gray-600" x-text="(r.timestampCheckin||'').slice(11,16)"></div>
                    </div>
                  </template>
                </div>

                {{-- Estados vacíos / loading --}}
                <div class="text-sm text-gray-500" x-show="loading">Cargando…</div>
                <div class="text-sm text-gray-500"
                     x-show="!loading && (!(detalles['{{ $row->codigoEmpleado }}']) || (detalles['{{ $row->codigoEmpleado }}'] || []).length===0)">
                  Sin movimientos.
                </div>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    <div class="mt-4">
      {{ $items->links() }}
    </div>
  </div>

  {{-- Alpine logic --}}
  <script>
    function fichajesPage(urlTpl, fecha) {
      return {
        abierto: null,
        detalles: {}, // cache { emp: array }
        loading: false,
        async toggle(emp) {
          // cerrar si ya está abierto
          if (this.abierto === emp) { this.abierto = null; return; }

          // abrir y preparar estado
          this.abierto = emp;

          // si ya hay cache, no vuelvas a pedir
          if (Array.isArray(this.detalles[emp])) return;

          // inicializar array vacío para evitar undefined
          this.detalles[emp] = [];
          this.loading = true;

          try {
            const url = urlTpl.replace('__EMP__', emp) + `?fecha=${encodeURIComponent(fecha)}&debug=1`;
            console.log('➡ fetch detalle', { url, emp, fecha });

            const resp = await fetch(url, { headers: { 'Accept': 'application/json' }});
            const data = await resp.json();
            console.log('⬅ detalle resp', data);

            const rows = Array.isArray(data) ? data : (data.rows ?? []);
            // normalizamos por seguridad (evitar undefined en campos usados)
            this.detalles[emp] = rows.map(r => ({
              timestampCheckin: r.timestampCheckin ?? '',
              timestampCheckout: r.timestampCheckout ?? '',
              valido: Number(r.valido ?? 0),
              motivo: r.motivo ?? '',
              codigoCliente: r.codigoCliente ?? '',
              clienteCalle: r.clienteCalle ?? ''
            }));

            if (!this.detalles[emp].length && data && data.debug) {
              alert(
                'Sin movimientos.\n' +
                `empleado: ${data.debug.empleado_param}\n` +
                `candidatos: ${data.debug.candidatos.join(',')}\n` +
                `fecha_req: ${data.debug.fecha_req}\n` +
                `count_exact: ${data.debug.count_exact}\n` +
                `fecha_fallback: ${data.debug.fecha_fallback}\n` +
                `count_fallback: ${data.debug.count_fallback}`
              );
            }
          } catch (e) {
            console.error('❌ detalle error', e);
            this.detalles[emp] = [];
          } finally {
            this.loading = false;
          }
        }
      }
    }
  </script>
</x-app-layout>
