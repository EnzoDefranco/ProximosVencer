<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class VencimientoValidacion extends Model
{
    protected $table = 'vencimientos_validaciones';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = ['item_id','checked','checked_by','checked_at'];
}
