<?php
namespace App\Models;

class HouseholdLinkRequest extends Document
{
    protected $table='household_link_requests';
    protected $casts=['reviewed_at'=>'datetime'];
    public function resident(){return $this->belongsTo(User::class,'resident_user_id');}
    public function zone(){return $this->belongsTo(Zone::class,'zone_id');}
    public function household(){return $this->belongsTo(Household::class,'household_id');}
}
