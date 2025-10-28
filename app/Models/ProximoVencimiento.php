<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ProximoVencimiento extends Model
{
    protected $connection = 'erp';
    protected $table = 'ENRO_DIGIP_articulosProximosAVencer';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'ArticuloCodigo','ArticuloDescripcion','fechaVencimiento',
        'fechaHoy','Unidades','diasRestantes','activo','last_sync_at'
    ];
}
