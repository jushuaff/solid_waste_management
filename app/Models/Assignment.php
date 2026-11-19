<?php
namespace App\Models;
class Assignment extends Document
{
    protected $casts = ['assigned_at'=>'datetime'];
    protected $table = 'assignments';

    public function report(){return $this->belongsTo(Report::class,'report_id');} public function crew(){return $this->belongsTo(User::class,'crew_user_id');}
}
