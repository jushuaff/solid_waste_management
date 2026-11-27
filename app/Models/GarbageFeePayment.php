<?php
namespace App\Models;

class GarbageFeePayment extends Document
{
    protected $table='garbage_fee_payments';
    protected $casts=['amount_centavos'=>'integer','paid_at'=>'datetime','voided_at'=>'datetime'];
    public function charge(){return $this->belongsTo(GarbageFeeCharge::class,'charge_id');}
}
