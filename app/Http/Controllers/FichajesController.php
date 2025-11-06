<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FichajesController extends Controller
{
    public function index(Request $r)
    {
        $conn = DB::connection('erp');
        $tablaCur = 'dw_reproc_tablas_aux.ENRO_AXUM_checkincheckoutCurrent';

        // 1) Última fecha disponible en CURRENT (fallback si no mandan ?fecha=)
        $ultimaFecha = $conn->table($tablaCur)->max('fecha');
        $fecha = $r->input('fecha') ?: $ultimaFecha;

        if (!$fecha) {
            // No hay datos en CURRENT aún
            return view('fichajes.index', [
                'items' => collect([]),
                'kpis'  => (object)[
                    'total_vendedores' => 0,
                    'a_tiempo'         => 0,
                    'total_checkins'   => 0,
                    'puntualidad_pct'  => 0,
                ],
                'fecha' => null,
                'zona'  => null,
                'zonas' => collect([]),
                'q'     => '',
            ]);
        }

        $zona = $r->input('zona');
        $q    = trim((string)$r->input('q', ''));

        // 2) Base query sobre la fecha elegida
        $query = $conn->table($tablaCur)->whereDate('fecha', $fecha);

        if ($zona) {
            $query->where('zonaId', $zona);
        }
        if ($q !== '') {
            $like = '%'.str_replace(['%','_'], ['\\%','\\_'], $q).'%';
            $query->where(function($w) use ($like, $q) {
                $w->where('nombreVendedor','like',$like)
                  ->orWhere('codigoEmpleado','like',$like)
                  ->orWhere('primerClienteId','like',$like)
                  ->orWhere('ultimoClienteId','like',$like);
            });
        }

        // 3) Paginado
        $items = (clone $query)
            ->orderBy('zonaDescripcion')
            ->orderBy('nombreVendedor')
            ->paginate(50)
            ->withQueryString();

        // 4) KPIs
        $kq = $conn->table($tablaCur)->whereDate('fecha', $fecha);
        if ($zona) {
            $kq->where('zonaId', $zona);
        }
        if ($q !== '') {
            $like = '%'.str_replace(['%','_'], ['\\%','\\_'], $q).'%';
            $kq->where(function($w) use ($like, $q) {
                $w->where('nombreVendedor','like',$like)
                  ->orWhere('codigoEmpleado','like',$like)
                  ->orWhere('primerClienteId','like',$like)
                  ->orWhere('ultimoClienteId','like',$like);
            });
        }

        $kpis = $kq->selectRaw('
                COUNT(*) as total_vendedores,
                SUM(llegoATiempo) as a_tiempo,
                SUM(totalCheckins) as total_checkins,
                ROUND(AVG(llegoATiempo)*100,0) as puntualidad_pct
            ')->first();

        // 5) Zonas para el combo (sólo de la fecha elegida)
        $zonas = $conn->table($tablaCur)
            ->whereDate('fecha', $fecha)
            ->select('zonaId as id','zonaDescripcion as nombre')
            ->distinct()
            ->orderBy('nombre')
            ->pluck('nombre','id');

        return view('fichajes.index', compact('items','kpis','fecha','zona','zonas','q'));
    }

    // JSON para el expandible "(+)"
public function detalle(Request $r, string $empleado)
{
    $conn = DB::connection('erp');

    // variantes del código (strings, por si hay ceros a la izquierda)
    $emp = trim((string)$empleado);
    $num = ltrim($emp, '0');
    $candidatos = collect([$emp, $num, str_pad($num,2,'0',STR_PAD_LEFT), str_pad($num,3,'0',STR_PAD_LEFT), str_pad($num,4,'0',STR_PAD_LEFT)])
        ->filter(fn($v) => $v !== '')
        ->unique()
        ->values()
        ->all();

    if (empty($candidatos)) {
        return response()->json(['rows'=>[], 'info'=>'empleado vacío']);
    }

    // fecha pedida o última disponible
    $fecha = $r->input('fecha');
    if (!$fecha) {
        $fecha = $conn->table('dw_reproc_tablas_aux.ENRO_AXUM_checkincheckoutSnapshoot')
            ->whereIn('codigoEmpleado', $candidatos)
            ->max('fechaSnapshot');
        if (!$fecha) return response()->json(['rows'=>[], 'info'=>'sin fechas para el empleado']);
    }

    try {
        $rows = $conn->table('dw_reproc_tablas_aux.ENRO_AXUM_checkincheckoutSnapshoot as s')
            // 🔧 JOIN forzando misma collation en ambos lados
            ->leftJoin('dw_vistas.st_enro_sigma_clientes as c', function ($j) {
                $j->on(
                    DB::raw('c.idCliente COLLATE utf8mb4_spanish_ci'),
                    '=',
                    DB::raw('s.codigoCliente COLLATE utf8mb4_spanish_ci')
                );
            })
            ->where('s.fechaSnapshot', '=', $fecha)
            ->where('s.deleted', 0)
            ->whereIn('s.codigoEmpleado', $candidatos) // params → no generan mix de collations
            ->orderBy('s.timestampCheckin')
            ->get([
                's.timestampCheckin',
                's.timestampCheckout',
                DB::raw('s.esValido as valido'),
                's.motivoCheckinInvalido as motivo',
                's.codigoCliente',
                DB::raw('c.calle as clienteCalle'),
            ]);

        return response()->json($rows);
    } catch (\Throwable $e) {
        return response()->json([
            'error' => true,
            'msg'   => $e->getMessage(),
        ], 200);
    }
}


}
