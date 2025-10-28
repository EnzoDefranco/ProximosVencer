<?php
namespace App\Http\Controllers;

use App\Models\ProximoVencimiento; // ERP
use App\Models\VencimientoValidacion; // App
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ItemController extends Controller
{
    public function index(Request $request)
    {
        $fechaHoy = $request->input('fechaHoy');

        $fechas = ProximoVencimiento::on('erp')
            ->select('fechaHoy')->distinct()->orderByDesc('fechaHoy')->pluck('fechaHoy');

        if (!$fechaHoy && $fechas->count() > 0) $fechaHoy = $fechas->first();

        $items = ProximoVencimiento::on('erp')
            ->when($fechaHoy, fn($q) => $q->where('fechaHoy', $fechaHoy))
            ->where('activo', 1)
            ->orderBy('fechaVencimiento')
            ->paginate(50)->appends(['fechaHoy' => $fechaHoy]);

        // Merge de checks (App DB) por los IDs visibles
        $ids = collect($items->items())->pluck('id')->all();
        $checks = VencimientoValidacion::whereIn('item_id', $ids)->pluck('checked', 'item_id');
        foreach ($items as $row) $row->checked = (int)($checks[$row->id] ?? 0);

        $ultimaSync = ProximoVencimiento::on('erp')->max('last_sync_at');
        $puedeEditar = Gate::allows('validar-vencimientos');

        return view('items.index', compact('items','fechas','fechaHoy','ultimaSync','puedeEditar'));
    }

    public function confirmar(Request $request)
    {
        abort_unless(Gate::allows('validar-vencimientos'), 403);

        $checkedIds = collect($request->input('checked', []))->map('intval');
        $visibleIds = collect($request->input('visible', []))->map('intval');
        $now = now();

        if ($checkedIds->isNotEmpty()) {
            $rows = $checkedIds->map(fn($id) => [
                'item_id'    => $id,
                'checked'    => 1,
                'checked_by' => $request->user()->id,
                'checked_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();
            VencimientoValidacion::upsert($rows, ['item_id'], ['checked','checked_by','checked_at','updated_at']);
        }

        if ($visibleIds->isNotEmpty()) {
            $noMarcados = $visibleIds->diff($checkedIds);
            if ($noMarcados->isNotEmpty()) {
                VencimientoValidacion::whereIn('item_id', $noMarcados)
                    ->update(['checked' => 0, 'updated_at' => $now]);
            }
        }

        return back()->with('ok','Cambios confirmados');
    }
}
