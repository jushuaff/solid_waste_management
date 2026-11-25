<?php
namespace App\Models;
class Household extends Document
{
    protected $table='households';
    protected $casts=['is_active'=>'boolean'];
    public function zone(){return $this->belongsTo(Zone::class,'zone_id');}
}
