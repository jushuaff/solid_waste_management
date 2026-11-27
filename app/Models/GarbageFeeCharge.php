<?php
namespace App\Models;

class GarbageFeeCharge extends Document
{
    protected $table='garbage_fee_charges';
    protected $casts=['amount_centavos'=>'integer'];
    public function household(){return $this->belongsTo(Household::class,'household_id');}
    public function city(){return $this->belongsTo(FeeCity::class,'city_id');}
    public function zone(){return $this->belongsTo(Zone::class,'zone_id');}
    public function payments(){return $this->hasMany(GarbageFeePayment::class,'charge_id');}
}
