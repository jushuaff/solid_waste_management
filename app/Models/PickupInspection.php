<?php
namespace App\Models;
class PickupInspection extends Document
{
    protected $table='pickup_inspections';
    protected $casts=['inspected_at'=>'datetime','segregated'=>'boolean','archived_at'=>'datetime'];
    public function zone(){return $this->belongsTo(Zone::class,'zone_id');}
    public function household(){return $this->belongsTo(Household::class,'household_id');}
    public function inspector(){return $this->belongsTo(User::class,'inspector_user_id');}
}
