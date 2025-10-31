<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ItemController extends Controller
{
    /**
     * GET /items
     * Muestra SOLO la última fechaHoy desde CURRENT.
     */
    public function index(Request $request)
    {
        $q = trim((string) $request->input('q'));

        // Última fechaHoy tomada de CURRENT
        $ultimaFecha = DB::connection('erp')
            ->table('dw_reproc_tablas_aux.ENRO_DIGIP_articulosVenc_current')
            ->max('fechaHoy');

        if (!$ultimaFecha) {
            return view('items.index', [
                'items'       => collect([]),
                'fechaHoy'    => null,
                'ultimaSync'  => null,
                'ultimoCheck' => null,
                'creadoAt'    => null,
                'puedeEditar' => Gate::allows('validar-vencimientos'),
                'q'           => $q,
                'stats'       => ['total'=>0,'validados'=>0,'urgentes'=>0,'porc'=>0],
                'ultimaFecha' => null,
            ]);
        }

        // Base query: CURRENT en la última fecha
        $qbBase = DB::connection('erp')
            ->table('dw_reproc_tablas_aux.ENRO_DIGIP_articulosVenc_current as p')
            ->where('p.activo', 1)
            ->whereDate('p.fechaHoy', $ultimaFecha)
            ->when($q !== '', function ($qq) use ($q) {
                $like = '%'.str_replace(['%','_'], ['\\%','\\_'], $q).'%';
                $qq->where(function ($w) use ($like, $q) {
                    $w->where('p.ArticuloCodigo', 'like', $like)
                      ->orWhere('p.ArticuloDescripcion', 'like', $like);
                    if (ctype_digit($q)) {
                        $w->orWhere('p.ArticuloCodigo', '=', $q);
                    }
                });
            });

        // KPIs
        $total     = (clone $qbBase)->count();
        $validados = (clone $qbBase)->where('p.checked', 1)->count();
        $urgentes  = (clone $qbBase)
            ->whereNotNull('p.diasRestantes')
            ->where('p.diasRestantes', '<=', 7)
            ->count();

        $stats = [
            'total'     => $total,
            'validados' => $validados,
            'urgentes'  => $urgentes,
            'porc'      => $total ? round($validados * 100 / $total) : 0,
        ];

        // Select + paginate (incluye delta_unidades del current)
        $items = (clone $qbBase)
            ->select([
                'p.id',
                'p.ArticuloCodigo',
                'p.ArticuloDescripcion',
                'p.fechaVencimiento',
                'p.fechaHoy',
                'p.Unidades',
                'p.diasRestantes',
                'p.checked',
                'p.created_at',
                'p.delta_unidades', // delta directo del ETL current
            ])
            ->orderBy('p.fechaVencimiento')
            ->paginate(50)
            ->appends(['q' => $q]);

        // Info superior (de CURRENT)
        $ultimaSync = DB::connection('erp')
            ->table('dw_reproc_tablas_aux.ENRO_DIGIP_articulosVenc_current')
            ->max('last_sync_at');

        $creadoAt = DB::connection('erp')
            ->table('dw_reproc_tablas_aux.ENRO_DIGIP_articulosVenc_current')
            ->min('created_at');

        $ultimoCheck = DB::connection('erp')
            ->table('dw_reproc_tablas_aux.ENRO_DIGIP_articulosVenc_current')
            ->whereNotNull('checked_at')
            ->max('checked_at');

        $puedeEditar = Gate::allows('validar-vencimientos');

        return view('items.index', compact(
            'items',
            'ultimaSync',
            'ultimoCheck',
            'creadoAt',
            'puedeEditar',
            'q',
            'stats',
            'ultimaFecha'
        ))->with('fechaHoy', $ultimaFecha);
    }

    /**
     * POST /items/confirmar
     * Actualiza checks en CURRENT (última fecha).
     */
    public function confirmar(Request $request)
    {
        abort_unless(Gate::allows('validar-vencimientos'), 403);

        $checkedIds = collect($request->input('checked', []))
            ->filter(fn($v) => $v !== null && $v !== '')
            ->map(fn($v) => (string) $v)
            ->unique()
            ->values();

        $visibleIds = collect($request->input('visible', []))
            ->filter(fn($v) => $v !== null && $v !== '')
            ->map(fn($v) => (string) $v)
            ->unique()
            ->values();

        if ($visibleIds->isEmpty()) {
            return back()->with('ok', 'No había filas visibles para actualizar.');
        }

        $noMarcados = $visibleIds->diff($checkedIds)->values();
        $userId     = $request->user()->id;
        $now        = now();

        DB::connection('erp')->transaction(function () use ($checkedIds, $noMarcados, $userId, $now) {
            if ($checkedIds->isNotEmpty()) {
                foreach ($checkedIds->chunk(500) as $chunk) {
                    DB::connection('erp')
                        ->table('dw_reproc_tablas_aux.ENRO_DIGIP_articulosVenc_current')
                        ->whereIn('id', $chunk->all())
                        ->update([
                            'checked'    => 1,
                            'checked_by' => $userId,
                            'checked_at' => $now,
                            'updated_at' => $now,
                        ]);
                }
            }
            if ($noMarcados->isNotEmpty()) {
                foreach ($noMarcados->chunk(500) as $chunk) {
                    DB::connection('erp')
                        ->table('dw_reproc_tablas_aux.ENRO_DIGIP_articulosVenc_current')
                        ->whereIn('id', $chunk->all())
                        ->update([
                            'checked'    => 0,
                            'checked_by' => null,
                            'checked_at' => null,
                            'updated_at' => $now,
                        ]);
                }
            }
        });

        $verificacion = DB::connection('erp')
            ->table('dw_reproc_tablas_aux.ENRO_DIGIP_articulosVenc_current')
            ->whereIn('id', $visibleIds)
            ->selectRaw('SUM(checked=1) as tildados, COUNT(*) as visibles')
            ->first();

        return back()->with(
            'ok',
            "Cambios confirmados. Tildados: {$verificacion->tildados}/{$verificacion->visibles}"
        );
    }

    /**
     * GET /items/{codigo}/historial
     * Devuelve HTML para hover o modal con snapshots de ese ARTÍCULO (todas las filas del snapshot).
     * Si ?compact=1, devuelve versión reducida para hovercard.
     */
    public function historial(Request $request, string $codigo)
    {
        $rows = DB::connection('erp')
            ->table('dw_reproc_tablas_aux.ENRO_DIGIP_articulosVenc_snapshot')
            ->where('ArticuloCodigo', $codigo)
            ->orderByDesc('fechaHoy')
            ->orderBy('fechaVencimiento')
            ->select([
                'ArticuloCodigo',
                'fechaVencimiento',
                'fechaHoy',
                'Unidades',
                'diasRestantes',
            ])
            ->get();

        if ((string)$request->query('compact') === '1') {
            // calcular delta histórico (entre snapshots)
            $rows = $rows->values();
            $withDelta = [];
            for ($i = 0; $i < count($rows); $i++) {
                $curr = (object) $rows[$i];
                $prev = $rows[$i+1] ?? null; // snapshot más viejo
                $curr->delta_unidades = null;
                if ($prev) {
                    $currUnits = is_null($curr->Unidades) ? 0 : (int)$curr->Unidades;
                    $prevUnits = is_null($prev->Unidades) ? 0 : (int)$prev->Unidades;
                    $curr->delta_unidades = $currUnits - $prevUnits;
                }
                $withDelta[] = $curr;
            }

            return view('items._historial_compact', [
                'codigo' => $codigo,
                'rows'   => collect($withDelta)->take(8), // últimos 8 para tooltip
            ]);
        }

        return view('items._historial', [
            'codigo' => $codigo,
            'rows'   => $rows,
        ]);
    }
}
