<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ItemController extends Controller
{
    /** GET /items — listado principal + KPIs */
    public function index(Request $request)
    {
        $q = trim((string)$request->input('q'));

        // Último corte en CURRENT
        $ultimaFecha = DB::connection('erp')
            ->table('dw_reproc_tablas_aux.ENRO_DIGIP_articulosProxVenc_current')
            ->max('fechaHoy');

        if (!$ultimaFecha) {
            return view('items.index', [
                'items'           => collect([]),
                'fechaHoy'        => null,
                'ultimaSync'      => null,
                'ultimoCheck'     => null,
                'creadoAt'        => null,
                'puedeEditar'     => Gate::allows('validar-vencimientos'),
                'q'               => $q,
                'stats'           => ['total'=>0,'validados'=>0,'urgentes'=>0,'porc'=>0],
                'kpiVencidos'     => 0,
                'kpiMovimientos'  => 0,
            ]);
        }

        // Base CURRENT (filtrado)
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
        $urgentes  = (clone $qbBase)->whereNotNull('p.diasRestantes')->where('p.diasRestantes','<=',7)->count();

        $stats = [
            'total'     => $total,
            'validados' => $validados,
            'urgentes'  => $urgentes,
            'porc'      => $total ? round($validados * 100 / $total) : 0,
        ];

        // Listado
        $items = (clone $qbBase)
            ->select([
                'p.id','p.ArticuloCodigo','p.ArticuloDescripcion','p.fechaVencimiento',
                'p.fechaHoy','p.Unidades','p.diasRestantes','p.checked','p.created_at',
                'p.delta_unidades',
            ])
            ->orderBy('p.fechaVencimiento')
            ->paginate(50)
            ->appends(['q'=>$q]);

        // Info superior
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

        $puedeEditar = Gate::allows('validar-vencimientos');

        // KPI Vencidos (desde SNAPSHOT)
        $fhSnap = DB::connection('erp')
            ->table('dw_reproc_tablas_aux.ENRO_DIGIP_articulosProxVenc_snapshot')
            ->max('fechaHoy') ?? $ultimaFecha;

        $kpiVencidos = DB::connection('erp')
            ->table('dw_reproc_tablas_aux.ENRO_DIGIP_articulosProxVenc_snapshot as s')
            ->whereDate('s.fechaHoy', $fhSnap)
            ->where('s.Unidades','>',0)
            ->whereColumn('s.fechaVencimiento','<=','s.fechaHoy')
            ->selectRaw('COUNT(DISTINCT CONCAT(s.ArticuloCodigo,"|",s.fechaVencimiento)) as c')
            ->value('c');

        // KPI Movimientos (corregidos+desaparecidos) del corte actual en histórico
        $kpiMovimientos = DB::connection('erp')
            ->table('dw_reproc_tablas_aux.ENRO_DIGIP_articulos_venc_cambios')
            ->whereDate('fh_actual', $fhSnap)
            ->count();

        return view('items.index', [
            'items'           => $items,
            'fechaHoy'        => $ultimaFecha,
            'ultimaSync'      => $ultimaSync,
            'ultimoCheck'     => $ultimoCheck,
            'creadoAt'        => $creadoAt,
            'puedeEditar'     => $puedeEditar,
            'q'               => $q,
            'stats'           => $stats,
            'kpiVencidos'     => (int)$kpiVencidos,
            'kpiMovimientos'  => (int)$kpiMovimientos,
        ]);
    }

    /** POST /items/confirmar — actualiza checks en CURRENT */
    public function confirmar(Request $request)
    {
        abort_unless(Gate::allows('validar-vencimientos'), 403);

        $checkedIds = collect($request->input('checked', []))->filter()->map(fn($v)=>(string)$v)->unique()->values();
        $visibleIds = collect($request->input('visible', []))->filter()->map(fn($v)=>(string)$v)->unique()->values();

        if ($visibleIds->isEmpty()) return back()->with('ok','No había filas visibles para actualizar.');

        $noMarcados = $visibleIds->diff($checkedIds)->values();
        $userId = $request->user()->id; $now = now();

        DB::connection('erp')->transaction(function () use ($checkedIds,$noMarcados,$userId,$now) {
            if ($checkedIds->isNotEmpty()) {
                foreach ($checkedIds->chunk(500) as $chunk) {
                    DB::connection('erp')
                        ->table('dw_reproc_tablas_aux.ENRO_DIGIP_articulosProxVenc_current')
                        ->whereIn('id',$chunk->all())
                        ->update([
                            'checked'=>1,'checked_by'=>$userId,'checked_at'=>$now,'updated_at'=>$now,
                        ]);
                }
            }
            if ($noMarcados->isNotEmpty()) {
                foreach ($noMarcados->chunk(500) as $chunk) {
                    DB::connection('erp')
                        ->table('dw_reproc_tablas_aux.ENRO_DIGIP_articulosProxVenc_current')
                        ->whereIn('id',$chunk->all())
                        ->update([
                            'checked'=>0,'checked_by'=>null,'checked_at'=>null,'updated_at'=>$now,
                        ]);
                }
            }
        });

        $v = DB::connection('erp')
            ->table('dw_reproc_tablas_aux.ENRO_DIGIP_articulosProxVenc_current')
            ->whereIn('id',$visibleIds)
            ->selectRaw('SUM(checked=1) tildados, COUNT(*) visibles')
            ->first();

        return back()->with('ok', "Cambios confirmados. Tildados: {$v->tildados}/{$v->visibles}");
    }

    /** GET /items/{codigo}/historial — hover: últimos snapshots para ese (código,vto) */
    public function historial(Request $request, string $codigo)
    {
        $vto     = $request->query('vto');              // YYYY-MM-DD (recomendado pasarla)
        $compact = (string)$request->query('compact') === '1';

        if (!$vto) {
            return view('items._historial_compact', ['codigo'=>$codigo,'rows'=>collect([])]);
        }

        // Traigo por (fechaHoy,fechaVencimiento) agregando unidades
        $grouped = DB::connection('erp')
            ->table('dw_reproc_tablas_aux.ENRO_DIGIP_articulosProxVenc_snapshot as s')
            ->where('s.ArticuloCodigo', $codigo)
            ->whereDate('s.fechaVencimiento', $vto)
            ->groupBy('s.fechaHoy','s.fechaVencimiento')
            ->orderBy('s.fechaHoy','desc')
            ->selectRaw('s.fechaHoy, s.fechaVencimiento, SUM(s.Unidades) Unidades, DATEDIFF(s.fechaVencimiento,s.fechaHoy) diasRestantes')
            ->limit(20)
            ->get();

        if ($grouped->isEmpty()) {
            return view('items._historial_compact', ['codigo'=>$codigo,'rows'=>collect([])]);
        }

        // Calcular Δ entre snapshots consecutivos
        $asc = $grouped->sortBy('fechaHoy')->values();
        $rowsDelta = collect(); $prev = null;
        foreach ($asc as $r) {
            $rowsDelta->push((object)[
                'fechaHoy'        => $r->fechaHoy,
                'fechaVencimiento'=> $r->fechaVencimiento,
                'Unidades'        => (int)$r->Unidades,
                'diasRestantes'   => $r->diasRestantes,
                'delta_unidades'  => is_null($prev)? null : ((int)$r->Unidades - (int)$prev),
            ]);
            $prev = (int)$r->Unidades;
        }

        $rows = $rowsDelta->sortByDesc('fechaHoy')->values();
        if ($compact) $rows = $rows->take(5);

        return view($compact ? 'items._historial_compact' : 'items._historial', [
            'codigo'=>$codigo,
            'rows'=>$rows
        ]);
    }

    /** POST /items/imprimir — imprime snapshot de no chequeados (mismo corte) */
    public function imprimirPendientes(Request $request)
    {
        abort_unless(\Gate::allows('validar-vencimientos'), 403);

        $fechaHoy = $request->input('fechaHoy') ?: DB::connection('erp')
            ->table('dw_reproc_tablas_aux.ENRO_DIGIP_articulosProxVenc_current')->max('fechaHoy');

        $codigos = DB::connection('erp')
            ->table('dw_reproc_tablas_aux.ENRO_DIGIP_articulosProxVenc_current as c')
            ->whereDate('c.fechaHoy', $fechaHoy)
            ->where('c.checked', 0)
            ->pluck('c.ArticuloCodigo')->unique()->values();

        if ($codigos->isEmpty()) {
            return back()->with('ok','No hay artículos pendientes para imprimir.');
        }

        $rows = DB::connection('erp')
            ->table('dw_reproc_tablas_aux.ENRO_DIGIP_articulosProxVenc_snapshot as s')
            ->whereIn('s.ArticuloCodigo', $codigos)
            ->whereDate('s.fechaHoy', $fechaHoy)
            ->orderBy('s.ArticuloCodigo')->orderBy('s.fechaVencimiento')->orderBy('s.Ubicacion')->orderBy('s.ContenedorNumero')
            ->select(['s.ArticuloCodigo','s.ArticuloDescripcion','s.fechaVencimiento','s.Unidades','s.diasRestantes','s.Ubicacion','s.ContenedorNumero'])
            ->get();

        return view('items.print', ['rows'=>$rows,'fechaHoy'=>$fechaHoy]);
    }

    /** GET /items/corregidos — pantalla que lee el histórico (tabla ERP) */
    public function corregidosHistorico(Request $request)
    {
        $fh = DB::connection('erp')
            ->table('dw_reproc_tablas_aux.ENRO_DIGIP_articulos_venc_cambios')
            ->max('fh_actual');

        $tipo   = $request->input('tipo');           // CORREGIDO / DESAPARECIDO / vacío
        $codigo = trim((string)$request->input('q'));// filtro por código opcional

        $rows = DB::connection('erp')
            ->table('dw_reproc_tablas_aux.ENRO_DIGIP_articulos_venc_cambios')
            ->when($fh, fn($q)=>$q->whereDate('fh_actual',$fh))
            ->when(in_array($tipo, ['CORREGIDO','DESAPARECIDO'], true),
                fn($q)=>$q->where('tipo',$tipo))
            ->when($codigo !== '',
                fn($q)=>$q->where('articulo_codigo','like',"%$codigo%"))
            ->orderBy('tipo')->orderBy('articulo_codigo')
            ->get();

        return view('items.corregidos', [
            'rows'     => $rows,
            'fechaHoy' => $fh,
            'filtros'  => ['tipo'=>$tipo,'q'=>$codigo],
        ]);
    }

    /** GET /items/vencidos — (opcional) lista de vencidos en 45 días */
    public function vencidosSnapshot(Request $request)
    {
        $fh = DB::connection('erp')
            ->table('dw_reproc_tablas_aux.ENRO_DIGIP_articulosProxVenc_snapshot')
            ->max('fechaHoy');

        if (!$fh) {
            return view('items.vencidos', ['rows'=>collect([]),'fechaHoy'=>null]);
        }

        $rows = DB::connection('erp')
            ->table('dw_reproc_tablas_aux.ENRO_DIGIP_articulosProxVenc_snapshot as s')
            ->whereDate('s.fechaHoy', $fh)->where('s.Unidades','>',0)
            ->whereRaw('DATEDIFF(?, s.fechaVencimiento) BETWEEN 0 AND 45', [$fh])
            ->select(['s.ArticuloCodigo','s.ArticuloDescripcion','s.fechaVencimiento','s.Unidades','s.diasRestantes','s.Ubicacion','s.ContenedorNumero'])
            ->orderBy('s.fechaVencimiento')->orderBy('s.ArticuloCodigo')->orderBy('s.Ubicacion')->orderBy('s.ContenedorNumero')
            ->paginate(200);

        return view('items.vencidos', ['rows'=>$rows,'fechaHoy'=>$fh]);
    }
}
