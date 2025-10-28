<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ItemController extends Controller
{
    /**
     * GET /items
     * Lee DIRECTO de la tabla del ETL (conexión 'erp'), calcula KPIs y pagina.
     */
    public function index(Request $request)
    {
        $fechaHoy = $request->input('fechaHoy');
        $q        = trim((string) $request->input('q')); // buscador opcional

        // Fechas disponibles (distinct) desde la tabla del ETL
        $fechas = DB::connection('erp')
            ->table('ENRO_DIGIP_articulosProximosAVencer')
            ->select('fechaHoy')
            ->distinct()
            ->orderByDesc('fechaHoy')
            ->pluck('fechaHoy');

        if (!$fechaHoy && $fechas->count() > 0) {
            $fechaHoy = $fechas->first();
        }

        // Query base (SIN select todavía) para poder clonar y contar
        $qbBase = DB::connection('erp')
            ->table('ENRO_DIGIP_articulosProximosAVencer as p')
            ->where('p.activo', 1)
            ->when($fechaHoy, fn($qq) => $qq->where('p.fechaHoy', $fechaHoy))
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

        // ---------- KPIs (sobre el filtro actual) ----------
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
        // ---------------------------------------------------

        // Ahora sí: select + order + paginate para la grilla
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
            ])
            ->orderBy('p.fechaVencimiento')
            ->paginate(50)
            ->appends(['fechaHoy' => $fechaHoy, 'q' => $q]);

        // Info de última sync (para el encabezado)
        $ultimaSync = DB::connection('erp')
            ->table('ENRO_DIGIP_articulosProximosAVencer')
            ->max('created_at');

        // Ultimo check en los items (para permisos de edición)
        $ultimoCheck = DB::connection('erp')
            ->table('ENRO_DIGIP_articulosProximosAVencer')
            ->whereNotNull('checked_at')
            ->max('checked_at');
        

        $puedeEditar = Gate::allows('validar-vencimientos');

        return view('items.index', compact(
            'items',
            'fechas',
            'fechaHoy',
            'ultimaSync',
            'ultimoCheck',
            'puedeEditar',
            'q',
            'stats'
        ));
    }

    /**
     * POST /items/confirmar
     * Marca/destilda checks en la MISMA tabla del ETL (por id).
     * Usa transacción y actualiza en lotes.
     */
    public function confirmar(Request $request)
    {
        abort_unless(Gate::allows('validar-vencimientos'), 403);

        // Arrays tal cual vienen del form (ids como STRING por BIGINT)
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

        // Todo-or-nada
        DB::connection('erp')->transaction(function () use ($checkedIds, $noMarcados, $userId, $now) {

            // Tildar seleccionados
            if ($checkedIds->isNotEmpty()) {
                foreach ($checkedIds->chunk(500) as $chunk) {
                    DB::connection('erp')
                        ->table('ENRO_DIGIP_articulosProximosAVencer')
                        ->whereIn('id', $chunk->all())
                        ->update([
                            'checked'    => 1,
                            'checked_by' => $userId,
                            'checked_at' => $now,
                            'updated_at' => $now,
                        ]);
                }
            }

            // Destildar los visibles que NO vinieron en checked[]
            if ($noMarcados->isNotEmpty()) {
                foreach ($noMarcados->chunk(500) as $chunk) {
                    DB::connection('erp')
                        ->table('ENRO_DIGIP_articulosProximosAVencer')
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

        // Verificación post-commit (diagnóstico rápido)
        $verificacion = DB::connection('erp')
            ->table('ENRO_DIGIP_articulosProximosAVencer')
            ->whereIn('id', $visibleIds)
            ->selectRaw('SUM(checked=1) as tildados, COUNT(*) as visibles')
            ->first();

        return back()->with(
            'ok',
            "Cambios confirmados. Tildados: {$verificacion->tildados}/{$verificacion->visibles}"
        );
    }
}
