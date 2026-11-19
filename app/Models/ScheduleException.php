<?php
namespace App\Models;
class ScheduleException extends Document
{
    protected $table = 'schedule_exceptions';

    public function schedule(){return $this->belongsTo(Schedule::class,'schedule_id');}
}
