<?php
namespace App\Models;

class FeeReceivingAccount extends Document
{
    protected $table='fee_receiving_accounts';
    protected $casts=[
        'is_active'=>'boolean',
        'change_history'=>'array',
    ];

    public function city(){return $this->belongsTo(FeeCity::class,'city_id');}
}
