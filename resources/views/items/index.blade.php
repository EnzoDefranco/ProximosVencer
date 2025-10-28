{{-- resources/views/items/index.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Próximos Vencimientos
        </h2>
    </x-slot>

    <div class="mx-auto max-w-7xl p-4 md:p-8">
        <div class="mb-2 text-sm text-gray-500">
            Última sincronización: {{ $ultimaSync ? \Carbon\Carbon::parse($ultimaSync)->format('d/m/Y H:i') : '—' }}
        </div>

        <form method="GET" action="{{ route('items.index') }}" class="mb-4 flex items-center gap-3">
            <label class="text-sm">Snapshot (fechaHoy):</label>
            <select name="fechaHoy" onchange="this.form.submit()" class="border rounded px-2 py-1">
                @foreach($fechas as $f)
                    <option value="{{ $f }}" @selected($f == $fechaHoy)>{{ \Carbon\Carbon::parse($f)->format('d/m/Y') }}</option>
                @endforeach
            </select>
        </form>

        @if (session('ok'))
            <div class="mb-4 rounded border border-green-300 bg-green-50 text-green-700 px-3 py-2">
                {{ session('ok') }}
            </div>
        @endif

        <form method="POST" action="{{ route('items.confirmar') }}">
            @csrf
            <input type="hidden" name="fechaHoy" value="{{ $fechaHoy }}">

            <div class="overflow-x-auto rounded border">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-100 text-left">
                        <tr>
                            <th class="p-2 w-12">OK</th>
                            <th class="p-2">Artículo</th>
                            <th class="p-2">Descripción</th>
                            <th class="p-2">Vence</th>
                            <th class="p-2 text-right">Unidades</th>
                            <th class="p-2 text-right">Días</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach ($items as $row)
                        <tr class="border-t">
                            <td class="p-2 text-center">
                                <input type="hidden" name="visible[]" value="{{ $row->id }}">
                                @if ($puedeEditar)
                                    <input type="checkbox" name="checked[]" value="{{ $row->id }}" @checked($row->checked) class="h-4 w-4">
                                @else
                                    <input type="checkbox" disabled @checked($row->checked) class="h-4 w-4">
                                @endif
                            </td>
                            <td class="p-2 font-mono">{{ $row->ArticuloCodigo }}</td>
                            <td class="p-2">{{ $row->ArticuloDescripcion }}</td>
                            <td class="p-2">{{ \Carbon\Carbon::parse($row->fechaVencimiento)->format('d/m/Y') }}</td>
                            <td class="p-2 text-right">{{ number_format($row->Unidades ?? 0, 0, ',', '.') }}</td>
                            <td class="p-2 text-right">
                                @php $d = $row->diasRestantes; @endphp
                                <span class="@if($d !== null && $d <= 7) text-red-600 font-semibold @endif">
                                    {{ $d ?? '—' }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex items-center justify-between">
                <div>{{ $items->links() }}</div>
                @if ($puedeEditar)
                    <button type="submit" class="rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700">
                        Confirmar cambios
                    </button>
                @endif
            </div>
        </form>
    </div>
</x-app-layout>
