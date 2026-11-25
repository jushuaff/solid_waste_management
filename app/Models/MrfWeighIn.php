<?php
namespace App\Models;
class MrfWeighIn extends Document
{
    protected $table='mrf_weigh_ins';
    protected $casts=['measured_at'=>'datetime','weight_kg'=>'float','diverted_kg'=>'float','archived_at'=>'datetime'];
    public function zone(){return $this->belongsTo(Zone::class,'zone_id');}
    public function category(){return $this->belongsTo(WasteCategory::class,'category_id');}
    public function recorder(){return $this->belongsTo(User::class,'recorded_by');}
}
