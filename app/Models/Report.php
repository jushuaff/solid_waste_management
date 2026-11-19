<?php
namespace App\Models;
class Report extends Document
{
    protected $casts = ['submitted_at'=>'datetime'];
    protected $table = 'reports';

    public function resident(){return $this->belongsTo(User::class,'resident_id');} public function zone(){return $this->belongsTo(Zone::class,'zone_id');} public function category(){return $this->belongsTo(WasteCategory::class,'category_id');} public function assignment(){return $this->hasOne(Assignment::class,'report_id')->latest('assigned_at');} public function histories(){return $this->hasMany(StatusHistory::class,'report_id')->orderBy('changed_at');}
}
