<?php
namespace App\Models;

class FeeCity extends Document
{
    protected $table='fee_cities';
    public function rates(){return $this->hasMany(FeeRate::class,'city_id');}
    public function zones(){return $this->hasMany(Zone::class,'fee_city_id');}
}
