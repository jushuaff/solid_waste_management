<?php
namespace App\Models;

class FeeRate extends Document
{
    protected $table='fee_rates';
    protected $casts=['amount_centavos'=>'integer'];
    public function city(){return $this->belongsTo(FeeCity::class,'city_id');}
}
