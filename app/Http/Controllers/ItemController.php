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
            ->table('dw_reproc_tablas_aux.ENRO_DIGIP_articulosProxVenc_current')
            ->max('fechaHoy');

        if (!$ultimaFecha) {
            return view('items.index', [
                'items'        => collect([]),
                'fechaHoy'     => null,
                'ultimaSync'   => null,
                'ultimoCheck'  => null,
                'creadoAt'     => null,
                'puedeEditar'  => Gate::allows('validar-vencimientos'),
                'q'            => $q,
                'stats'        => ['total'=>0,'validados'=>0,'urgentes'=>0,'porc'=>0],
                'ultimaFecha'  => null,
                'kpiVencidos'  => 0,
            ]);
        }

        // Base query: CURRENT en la última fecha
        $qbBase = DB::connection('erp')
            ->table('dw_reproc_tablas_aux.ENRO_DIGIP_articulosProxVenc_current as p')
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
                'p.delta_unidades',
            ])
            ->orderBy('p.fechaVencimiento')
            ->paginate(50)
            ->appends(['q' => $q]);

        // Info superior (de CURRENT)
        $ultimaSync = DB::connection('erp')
            ->table('dw_reproc_tablas_aux.ENRO_DIGIP_articulosProxVenc_current')
            ->max('last_sync_at');

        $creadoAt = DB::connection('erp')
            ->table('dw_reproc_tablas_aux.ENRO_DIGIP_articulosProxVenc_current')
            ->min('created_at');

        $ultimoCheck = DB::connection('erp')
            ->table('dw_reproc_tablas_aux.ENRO_DIGIP_articulosProxVenc_current')
            ->whereNotNull('checked_at')
            ->max('checked_at');

        // KPI vencidos (desde SNAPSHOT, agrupando por código+vto en el último corte del snapshot)
        $fhSnap = DB::connection('erp')
            ->table('dw_reproc_tablas_aux.ENRO_DIGIP_articulosProxVenc_snapshot')
            ->max('fechaHoy') ?? $ultimaFecha;

        $kpiVencidos = DB::connection('erp')
            ->table('dw_reproc_tablas_aux.ENRO_DIGIP_articulosProxVenc_snapshot as s')
            ->whereDate('s.fechaHoy', $fhSnap)
            ->where('s.Unidades', '>', 0)
            ->whereColumn('s.fechaVencimiento', '<=', 's.fechaHoy')
            ->selectRaw('COUNT(DISTINCT CONCAT(s.ArticuloCodigo,"|",s.fechaVencimiento)) as c')
            ->value('c');

        $puedeEditar = Gate::allows('validar-vencimientos');

        return view('items.index', [
            'items'        => $items,
            'fechaHoy'     => $ultimaFecha,
            'ultimaSync'   => $ultimaSync,
            'ultimoCheck'  => $ultimoCheck,
            'creadoAt'     => $creadoAt,
            'puedeEditar'  => $puedeEditar,
            'q'            => $q,
            'stats'        => $stats,
            'ultimaFecha'  => $ultimaFecha,
            'kpiVencidos'  => (int) $kpiVencidos,
        ]);
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
                        ->table('dw_reproc_tablas_aux.ENRO_DIGIP_articulosProxVenc_current')
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
                        ->table('dw_reproc_tablas_aux.ENRO_DIGIP_articulosProxVenc_current')
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
            ->table('dw_reproc_tablas_aux.ENRO_DIGIP_articulosProxVenc_current')
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
     * Devuelve HTML para hover o modal con snapshots de ese ARTÍCULO + VTO (agrupado por corte).
     * Si ?compact=1, devuelve versión reducida (últimas 5).
     * Requiere ?vto=YYYY-MM-DD
     */
    public function historial(Request $request, string $codigo)
    {
        $vto     = $request->query('vto');              // yyyy-mm-dd
        $compact = (string) $request->query('compact') === '1';

        if (!$vto) {
            return view('items._historial_compact', [
                'codigo' => $codigo,
                'rows'   => collect([]),
            ]);
        }

        // Agrupar por corte: sum(Unidades) y diasRestantes para ese Articulo+Vencimiento
        $grouped = DB::connection('erp')
            ->table('dw_reproc_tablas_aux.ENRO_DIGIP_articulosProxVenc_snapshot as s')
            ->where('s.ArticuloCodigo', $codigo)
            ->whereDate('s.fechaVencimiento', $vto)
            ->groupBy('s.fechaHoy', 's.fechaVencimiento')
            ->orderBy('s.fechaHoy', 'desc')
            ->selectRaw("
                s.fechaHoy,
                s.fechaVencimiento,
                SUM(s.Unidades) as Unidades,
                DATEDIFF(s.fechaVencimiento, s.fechaHoy) as diasRestantes
            ")
            ->limit(20)
            ->get();

        if ($grouped->isEmpty()) {
            return view('items._historial_compact', [
                'codigo' => $codigo,
                'rows'   => collect([]),
            ]);
        }

        // Calcular delta entre snapshots consecutivos (compatibilidad sin LAG())
        $asc = $grouped->sortBy('fechaHoy')->values();
        $rowsDelta = collect();
        $prevUnid = null;

        foreach ($asc as $row) {
            $r = (object)[
                'fechaHoy'         => $row->fechaHoy,
                'fechaVencimiento' => $row->fechaVencimiento,
                'Unidades'         => (int)$row->Unidades,
                'diasRestantes'    => $row->diasRestantes,
                'delta_unidades'   => is_null($prevUnid) ? null : ((int)$row->Unidades - (int)$prevUnid),
            ];
            $rowsDelta->push($r);
            $prevUnid = (int)$row->Unidades;
        }

        $rows = $rowsDelta->sortByDesc('fechaHoy')->values();

        if ($compact) {
            $rows = $rows->take(5);
            return view('items._historial_compact', [
                'codigo' => $codigo,
                'rows'   => $rows,
            ]);
        }

        return view('items._historial', [
            'codigo' => $codigo,
            'rows'   => $rows,
        ]);
    }

    /**
     * POST /items/imprimir
     * Imprime snapshot de códigos NO chequeados en CURRENT (mismo corte).
     */
    public function imprimirPendientes(Request $request)
    {
        abort_unless(\Gate::allows('validar-vencimientos'), 403);

        $fechaHoy = $request->input('fechaHoy') ?: \DB::connection('erp')
            ->table('dw_reproc_tablas_aux.ENRO_DIGIP_articulosProxVenc_current')
            ->max('fechaHoy');

        // todos los códigos no chequeados en ese corte
        $codigos = \DB::connection('erp')
            ->table('dw_reproc_tablas_aux.ENRO_DIGIP_articulosProxVenc_current as c')
            ->whereDate('c.fechaHoy', $fechaHoy)
            ->where('c.checked', 0)
            ->pluck('c.ArticuloCodigo')
            ->unique()
            ->values();

        if ($codigos->isEmpty()) {
            return back()->with('ok', 'No hay artículos pendientes para imprimir.');
        }

        $rows = \DB::connection('erp')
            ->table('dw_reproc_tablas_aux.ENRO_DIGIP_articulosProxVenc_snapshot as s')
            ->whereIn('s.ArticuloCodigo', $codigos)
            ->whereDate('s.fechaHoy', $fechaHoy)
            ->orderBy('s.ArticuloCodigo')
            ->orderBy('s.fechaVencimiento')
            ->orderBy('s.Ubicacion')
            ->orderBy('s.ContenedorNumero')
            ->select([
                's.ArticuloCodigo',
                's.ArticuloDescripcion',
                's.fechaVencimiento',
                's.Unidades',
                's.diasRestantes',
                's.Ubicacion',
                's.ContenedorNumero',
            ])->get();

        return view('items.print', [
            'rows'     => $rows,
            'fechaHoy' => $fechaHoy,
        ]);
    }

    /**
     * GET /items/vencidos
     * Vencidos en los últimos 45 días (desde snapshot) agrupados por ítem.
     */
    public function vencidosSnapshot(Request $request)
    {
        $fh = \DB::connection('erp')
            ->table('dw_reproc_tablas_aux.ENRO_DIGIP_articulosProxVenc_snapshot')
            ->max('fechaHoy');

        if (!$fh) {
            return view('items.vencidos', [
                'rows'     => collect([]),
                'fechaHoy' => null,
            ]);
        }

        $rows = \DB::connection('erp')
            ->table('dw_reproc_tablas_aux.ENRO_DIGIP_articulosProxVenc_snapshot as s')
            ->whereDate('s.fechaHoy', $fh)
            ->where('s.Unidades', '>', 0)
            ->whereRaw('DATEDIFF(?, s.fechaVencimiento) BETWEEN 0 AND 45', [$fh])
            ->select([
                's.ArticuloCodigo',
                's.ArticuloDescripcion',
                's.fechaVencimiento',
                's.Unidades',
                's.diasRestantes',
                's.Ubicacion',
                's.ContenedorNumero',
            ])
            ->orderBy('s.fechaVencimiento')
            ->orderBy('s.ArticuloCodigo')
            ->orderBy('s.Ubicacion')
            ->orderBy('s.ContenedorNumero')
            ->paginate(200);

        return view('items.vencidos', [
            'rows'     => $rows,
            'fechaHoy' => $fh,
        ]);
    }
}
