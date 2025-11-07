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

        // Última fechaHoy tomada de CURRENT (nueva tabla)
        $ultimaFecha = DB::connection('erp')
            ->table('dw_reproc_tablas_aux.ENRO_DIGIP_articulosProxVenc_current')
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

        // Base query: CURRENT en la última fecha (sin 'activo')
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
                'p.delta_unidades', // delta directo del ETL current
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
     * Devuelve HTML para hover o modal con snapshots de ese ARTÍCULO (todas las filas del snapshot).
     * Si ?compact=1, devuelve versión reducida para hovercard (últimas 5).
     */
    public function historial(Request $request, string $codigo)
    {
         // Último corte disponible en CURRENT
    $ultimaFecha = DB::connection('erp')
        ->table('dw_reproc_tablas_aux.ENRO_DIGIP_articulosProxVenc_current')
        ->max('fechaHoy');

    if (!$ultimaFecha) {
        return view('items._historial', [
            'codigo' => $codigo,
            'rows'   => collect([]),
        ]);
    }

    // Base: todas las filas del CURRENT (mismo corte) para ese artículo
    $qb = DB::connection('erp')
        ->table('dw_reproc_tablas_aux.ENRO_DIGIP_articulosProxVenc_current as c')
        ->where('c.ArticuloCodigo', $codigo)
        ->whereDate('c.fechaHoy', $ultimaFecha)
        ->orderBy('c.fechaVencimiento')
        ->select([
            'c.ArticuloCodigo',
            'c.fechaVencimiento',
            'c.fechaHoy',
            'c.Unidades',
            'c.diasRestantes',
            'c.delta_unidades',   // viene precalculado en CURRENT
        ]);

    // Versión compacta (para hover): solo 5 filas
    if ((string) $request->query('compact') === '1') {
        $rows = $qb->limit(5)->get();
        return view('items._historial_compact', [
            'codigo' => $codigo,
            'rows'   => $rows,
        ]);
    }

    // Versión completa
    $rows = $qb->get();
    return view('items._historial', [
        'codigo' => $codigo,
        'rows'   => $rows,
    ]);
    }

    /**
     * POST /items/imprimir-seleccionados
     * Imprime snapshot de artículos tildados en current (mismo corte).
     */

// public function imprimirSeleccionados(Request $request)
// {
//     abort_unless(\Gate::allows('validar-vencimientos'), 403);

//     $ids = collect($request->input('checked', []))->filter()->unique()->values();
//     if ($ids->isEmpty()) {
//         return back()->with('ok', 'No se seleccionaron artículos.');
//     }

//     $fechaHoy = $request->input('fechaHoy');
//     if (!$fechaHoy) {
//         $fechaHoy = \DB::connection('erp')
//             ->table('dw_reproc_tablas_aux.ENRO_DIGIP_articulosProxVenc_current')
//             ->max('fechaHoy');
//     }

//     // 1) Current tildados (para obtener los códigos elegidos)
//     $currentRows = \DB::connection('erp')
//         ->table('dw_reproc_tablas_aux.ENRO_DIGIP_articulosProxVenc_current as c')
//         ->whereIn('c.id', $ids)
//         ->select(['c.ArticuloCodigo'])
//         ->get();

//     if ($currentRows->isEmpty()) {
//         return back()->with('ok', 'No se encontraron filas en CURRENT.');
//     }

//     $codigos = $currentRows->pluck('ArticuloCodigo')->unique()->values();

//     // 2) Traer SOLO snapshot de esos códigos en ese corte (PLANO para tabla)
//     $rows = \DB::connection('erp')
//         ->table('dw_reproc_tablas_aux.ENRO_DIGIP_articulosProxVenc_snapshot as s')
//         ->whereIn('s.ArticuloCodigo', $codigos)
//         ->whereDate('s.fechaHoy', $fechaHoy)
//         ->orderBy('s.ArticuloCodigo')
//         ->orderBy('s.fechaVencimiento')
//         ->orderBy('s.Ubicacion')
//         ->orderBy('s.ContenedorNumero')
//         ->select([
//             's.ArticuloCodigo',
//             's.ArticuloDescripcion',
//             's.fechaVencimiento',
//             's.Unidades',
//             's.diasRestantes',
//             's.Ubicacion',
//             's.ContenedorNumero',
//         ])->get();

//     return view('items.print', [
//         'rows'    => $rows,
//         'fechaHoy'=> $fechaHoy,
//     ]);
// }

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

}
