<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Carbon\Carbon;

class ItemController extends Controller
{
    /** GET /items — listado principal + KPIs */
    public function index(Request $request)
    {
        $q = trim((string) $request->input('q'));

        // Último corte en CURRENT
        $ultimaFecha = DB::connection('erp')
            ->table('dw_reproc_tablas_aux.ENRO_DIGIP_articulosProxVenc_current')
            ->max('fechaHoy');

        if (!$ultimaFecha) {
            return view('items.index', [
                'items' => collect([]),
                'fechaHoy' => null,
                'ultimaSync' => null,
                'ultimoCheck' => null,
                'creadoAt' => null,
                'puedeEditar' => Gate::allows('validar-vencimientos'),
                'q' => $q,
                'stats' => ['total' => 0, 'validados' => 0, 'urgentes' => 0, 'porc' => 0],
                'kpiVencidos' => 0,
                'kpiMovimientos' => 0, // ahora será "vencen en 30 días"
            ]);
        }

        // Base CURRENT (filtros)
        $qbBase = DB::connection('erp')
            ->table('dw_reproc_tablas_aux.ENRO_DIGIP_articulosProxVenc_current as p')
            ->whereDate('p.fechaHoy', $ultimaFecha)
            ->when($q !== '', function ($qq) use ($q) {
                $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
                $qq->where(function ($w) use ($like, $q) {
                    $w->where('p.ArticuloCodigo', 'like', $like)
                        ->orWhere('p.ArticuloDescripcion', 'like', $like);
                    if (ctype_digit($q)) {
                        $w->orWhere('p.ArticuloCodigo', '=', $q);
                    }
                });
            });

        // Stats
        $total = (clone $qbBase)->count();
        $validados = (clone $qbBase)->where('p.checked', 1)->count();
        $urgentes = (clone $qbBase)->whereNotNull('p.diasRestantes')
            ->where('p.diasRestantes', '<=', 7)
            ->count();

        $stats = [
            'total' => $total,
            'validados' => $validados,
            'urgentes' => $urgentes,
            'porc' => $total ? round($validados * 100 / $total) : 0,
        ];

        // Listado principal
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

        // Metadatos del corte
        $metaBase = DB::connection('erp')
            ->table('dw_reproc_tablas_aux.ENRO_DIGIP_articulosProxVenc_current')
            ->whereDate('fechaHoy', $ultimaFecha);

        $ultimaSync = (clone $metaBase)->max('last_sync_at');
        $creadoAt = (clone $metaBase)->min('created_at');
        $ultimoCheck = (clone $metaBase)->max('checked_at');

        $puedeEditar = Gate::allows('validar-vencimientos');

        /*
        |----------------------------------------------------------------------
        | KPI 1: Artículos vencidos en los últimos 7 días
        | Lógica: fechaVencimiento entre HOY-7 y HOY (tabla de control)
        |----------------------------------------------------------------------
        */
        $hoy = now()->toDateString();
        $desde7 = now()->subDays(7)->toDateString();

        $kpiVencidos = DB::connection('erp')
            ->table('dw_reproc_tablas_aux.ENRO_DIGIP_articulosVencidos_control as c')
            ->whereBetween('c.fechaVencimiento', [$desde7, $hoy])
            ->selectRaw('COUNT(DISTINCT CONCAT(c.ArticuloCodigo,"|",c.fechaVencimiento)) as c')
            ->value('c');

        /*
        |----------------------------------------------------------------------
        | KPI 2 (antes "corregidos"): Artículos que vencen en los próximos 30 días
        | Lógica: fechaVencimiento entre HOY y HOY+30 en CURRENT (último corte)
        |----------------------------------------------------------------------
        */
        $hasta30 = now()->addDays(30)->toDateString();

        $kpiProx30 = DB::connection('erp')
            ->table('dw_reproc_tablas_aux.ENRO_DIGIP_articulosProxVenc_current as p2')
            ->whereDate('p2.fechaHoy', $ultimaFecha)
            ->whereBetween('p2.fechaVencimiento', [$hoy, $hasta30])
            ->selectRaw('COUNT(DISTINCT CONCAT(p2.ArticuloCodigo,"|",p2.fechaVencimiento)) as c')
            ->value('c');

        return view('items.index', [
            'items' => $items,
            'fechaHoy' => $ultimaFecha,
            'ultimaSync' => $ultimaSync,
            'ultimoCheck' => $ultimoCheck,
            'creadoAt' => $creadoAt,
            'puedeEditar' => $puedeEditar,
            'q' => $q,
            'stats' => $stats,
            'kpiVencidos' => (int) $kpiVencidos,
            // IMPORTANTE: acá reutilizamos el slot kpiMovimientos
            // pero ahora significa "artículos que vencen en los próximos 30 días"
            'kpiMovimientos' => (int) $kpiProx30,
        ]);
    }

    /** GET /items/exportar — descarga Excel (HTML table) */
    public function exportar(Request $request)
    {
        $q = trim((string) $request->input('q'));

        // Último corte en CURRENT
        $ultimaFecha = DB::connection('erp')
            ->table('dw_reproc_tablas_aux.ENRO_DIGIP_articulosProxVenc_current')
            ->max('fechaHoy');

        if (!$ultimaFecha) {
            return back()->with('ok', 'No hay datos para exportar.');
        }

        // Base CURRENT (filtros)
        $qbBase = DB::connection('erp')
            ->table('dw_reproc_tablas_aux.ENRO_DIGIP_articulosProxVenc_current as p')
            ->whereDate('p.fechaHoy', $ultimaFecha)
            ->when($q !== '', function ($qq) use ($q) {
                $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
                $qq->where(function ($w) use ($like, $q) {
                    $w->where('p.ArticuloCodigo', 'like', $like)
                        ->orWhere('p.ArticuloDescripcion', 'like', $like);
                    if (ctype_digit($q)) {
                        $w->orWhere('p.ArticuloCodigo', '=', $q);
                    }
                });
            });

        $items = (clone $qbBase)
            ->select([
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
            ->get();

        $filename = 'proximos_vencimientos_' . now()->format('Ymd_His') . '.xls';

        return response(view('items.export', [
            'items' => $items,
            'fechaHoy' => $ultimaFecha
        ]))
            ->header('Content-Type', 'application/vnd.ms-excel; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    /** POST /items/confirmar — actualiza checks en CURRENT */
    public function confirmar(Request $request)
    {
        abort_unless(Gate::allows('validar-vencimientos'), 403);

        $checkedIds = collect($request->input('checked', []))->filter()
            ->map(fn($v) => (string) $v)->unique()->values();

        $visibleIds = collect($request->input('visible', []))->filter()
            ->map(fn($v) => (string) $v)->unique()->values();

        if ($visibleIds->isEmpty()) {
            return back()->with('ok', 'No había filas visibles para actualizar.');
        }

        $noMarcados = $visibleIds->diff($checkedIds)->values();
        $userId = $request->user()->id;
        $now = now();

        DB::connection('erp')->transaction(function () use ($checkedIds, $noMarcados, $userId, $now) {
            if ($checkedIds->isNotEmpty()) {
                foreach ($checkedIds->chunk(500) as $chunk) {
                    DB::connection('erp')
                        ->table('dw_reproc_tablas_aux.ENRO_DIGIP_articulosProxVenc_current')
                        ->whereIn('id', $chunk->all())
                        ->update([
                            'checked' => 1,
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
                            'checked' => 0,
                            'checked_by' => null,
                            'checked_at' => null,
                            'updated_at' => $now,
                        ]);
                }
            }
        });

        $v = DB::connection('erp')
            ->table('dw_reproc_tablas_aux.ENRO_DIGIP_articulosProxVenc_current')
            ->whereIn('id', $visibleIds)
            ->selectRaw('SUM(checked=1) tildados, COUNT(*) visibles')
            ->first();

        return back()->with('ok', "Cambios confirmados. Tildados: {$v->tildados}/{$v->visibles}");
    }

    /** GET /items/{codigo}/historial — hover: últimos snapshots */
    public function historial(Request $request, string $codigo)
    {
        $vto = $request->query('vto');
        $compact = (string) $request->query('compact') === '1';

        if (!$vto) {
            return view('items._historial_compact', ['codigo' => $codigo, 'rows' => collect([])]);
        }

        $grouped = DB::connection('erp')
            ->table('dw_reproc_tablas_aux.ENRO_DIGIP_articulosProxVenc_snapshot as s')
            ->where('s.ArticuloCodigo', $codigo)
            ->whereDate('s.fechaVencimiento', $vto)
            ->groupBy('s.fechaHoy', 's.fechaVencimiento')
            ->orderBy('s.fechaHoy', 'desc')
            ->selectRaw('s.fechaHoy, s.fechaVencimiento, SUM(s.Unidades) Unidades, DATEDIFF(s.fechaVencimiento,s.fechaHoy) diasRestantes')
            ->limit(20)
            ->get();

        if ($grouped->isEmpty()) {
            return view('items._historial_compact', ['codigo' => $codigo, 'rows' => collect([])]);
        }

        $asc = $grouped->sortBy('fechaHoy')->values();
        $rowsDelta = collect();
        $prev = null;

        foreach ($asc as $r) {
            $rowsDelta->push((object) [
                'fechaHoy' => $r->fechaHoy,
                'fechaVencimiento' => $r->fechaVencimiento,
                'Unidades' => (int) $r->Unidades,
                'diasRestantes' => $r->diasRestantes,
                'delta_unidades' => is_null($prev) ? null : ((int) $r->Unidades - (int) $prev),
            ]);
            $prev = (int) $r->Unidades;
        }

        $rows = $rowsDelta->sortByDesc('fechaHoy')->values();
        if ($compact)
            $rows = $rows->take(5);

        return view($compact ? 'items._historial_compact' : 'items._historial', [
            'codigo' => $codigo,
            'rows' => $rows
        ]);
    }

    /** POST /items/imprimir — imprime snapshot de no chequeados */
    public function imprimirPendientes(Request $request)
    {
        abort_unless(\Gate::allows('validar-vencimientos'), 403);

        $fechaHoy = $request->input('fechaHoy') ?: DB::connection('erp')
            ->table('dw_reproc_tablas_aux.ENRO_DIGIP_articulosProxVenc_current')
            ->max('fechaHoy');

        $codigos = DB::connection('erp')
            ->table('dw_reproc_tablas_aux.ENRO_DIGIP_articulosProxVenc_current as c')
            ->whereDate('c.fechaHoy', $fechaHoy)
            ->where('c.checked', 0)
            ->pluck('c.ArticuloCodigo')->unique()->values();

        if ($codigos->isEmpty()) {
            return back()->with('ok', 'No hay artículos pendientes para imprimir.');
        }

        $rows = DB::connection('erp')
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
                's.ContenedorNumero'
            ])
            ->get();

        return view('items.print', [
            'rows' => $rows,
            'fechaHoy' => $fechaHoy
        ]);
    }

    public function vencidos(Request $request)
    {
        // Filtros
        $hoy = Carbon::today()->toDateString();
        $desde = $request->input('desde') ?: Carbon::today()->subDays(30)->toDateString();
        $hasta = $request->input('hasta') ?: $hoy;
        $q = trim((string) $request->input('q'));

        $query = DB::connection('erp')
            ->table('dw_reproc_tablas_aux.ENRO_DIGIP_articulosVencidos_control as v')
            ->when($desde, fn($q2) => $q2->whereDate('v.fechaVencimiento', '>=', $desde))
            ->when($hasta, fn($q2) => $q2->whereDate('v.fechaVencimiento', '<=', $hasta))
            ->when($q !== '', function ($q2) use ($q) {
                $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
                $q2->where(function ($w) use ($like, $q) {
                    $w->where('v.ArticuloCodigo', 'like', $like)
                        ->orWhere('v.ArticuloDescripcion', 'like', $like);
                    if (ctype_digit($q)) {
                        $w->orWhere('v.ArticuloCodigo', '=', $q);
                    }
                });
            });

        // KPIs
        $kpiTotalArt = (clone $query)
            ->selectRaw('COUNT(DISTINCT CONCAT(v.ArticuloCodigo,"|",v.fechaVencimiento)) as c')
            ->value('c');

        $kpiUnidades = (clone $query)
            ->selectRaw('SUM(v.unidadesPrimerVencido) as u')
            ->value('u');

        // Listado paginado
        $rows = $query
            ->select([
                'v.ArticuloCodigo',
                'v.ArticuloDescripcion',
                'v.fechaVencimiento',
                'v.fechaPrimerVencido',
                'v.unidadesPrimerVencido',
                'v.UbicacionEjemplo',
                'v.ContenedorEjemplo',
            ])
            ->orderBy('v.fechaVencimiento')
            ->orderBy('v.ArticuloCodigo')
            ->paginate(100)
            ->appends([
                'desde' => $desde,
                'hasta' => $hasta,
                'q' => $q,
            ]);

        return view('vencidos.index', [
            'rows' => $rows,
            'desde' => $desde,
            'hasta' => $hasta,
            'q' => $q,
            'kpiTotalArt' => (int) ($kpiTotalArt ?? 0),
            'kpiUnidades' => (int) ($kpiUnidades ?? 0),
        ]);
    }

    /**
     * POST /vencidos/print — imprime el mismo listado filtrado
     */
    public function imprimirVencidos(Request $request)
    {
        $hoy = Carbon::today()->toDateString();
        $desde = $request->input('desde') ?: Carbon::today()->subDays(30)->toDateString();
        $hasta = $request->input('hasta') ?: $hoy;
        $q = trim((string) $request->input('q'));

        $query = DB::connection('erp')
            ->table('dw_reproc_tablas_aux.ENRO_DIGIP_articulosVencidos_control as v')
            ->when($desde, fn($q2) => $q2->whereDate('v.fechaVencimiento', '>=', $desde))
            ->when($hasta, fn($q2) => $q2->whereDate('v.fechaVencimiento', '<=', $hasta))
            ->when($q !== '', function ($q2) use ($q) {
                $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
                $q2->where(function ($w) use ($like, $q) {
                    $w->where('v.ArticuloCodigo', 'like', $like)
                        ->orWhere('v.ArticuloDescripcion', 'like', $like);
                    if (ctype_digit($q)) {
                        $w->orWhere('v.ArticuloCodigo', '=', $q);
                    }
                });
            });

        $rows = $query
            ->select([
                'v.ArticuloCodigo',
                'v.ArticuloDescripcion',
                'v.fechaVencimiento',
                'v.fechaPrimerVencido',
                'v.unidadesPrimerVencido',
                'v.UbicacionEjemplo',
                'v.ContenedorEjemplo',
            ])
            ->orderBy('v.fechaVencimiento')
            ->orderBy('v.ArticuloCodigo')
            ->get();

        return view('vencidos.print', [
            'rows' => $rows,
            'desde' => $desde,
            'hasta' => $hasta,
            'q' => $q,
        ]);
    }

}
